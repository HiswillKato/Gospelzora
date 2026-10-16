<?php
/**
 * DB — the mysqli data layer.
 *
 * One instance per request, built by app/bootstrap.php. Every read goes through
 * select()/row()/scalar(), every write through execute()/insert()/affected(),
 * always parameterized with an array of plain values: the bind types are
 * detected from the PHP types, so no caller ever builds a type string by hand.
 *
 * TWO BEHAVIOURS THAT ARE EASY TO GET WRONG:
 *
 * 1. THE CONNECTION IS LAZY. The constructor does not connect; the first query
 *    does. bootstrap.php wires all seven objects before any of them touches the
 *    database, and it relies on a DB outage reaching the maintenance gate's own
 *    try/catch as a catchable throwable rather than dying while wiring. So
 *    conn() throws, it does not return null.
 *
 * 2. errno() REPORTS THE LAST STATEMENT, NOT A STICKY ERROR. Callers use it
 *    right after a write to turn a driver error into finished display text
 *    ("A category with that name already exists." for 1062). That only works if
 *    a successful statement clears it, which is why every path resets $errno.
 */

class DB
{
    /** Opened on the first query, not in the constructor. */
    private ?mysqli $connection = null;

    /** Driver error number for the most recent statement; 0 when it worked. */
    private int $errno = 0;

    /** Driver error text for the most recent statement. */
    private string $error = '';

    /** Set once the DB is known to be unreachable, so we stop retrying. */
    private bool $dead = false;

    /**
     * Connect on demand.
     *
     * mysqli_report(MYSQLI_REPORT_OFF) is deliberate: with the default
     * MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT the driver throws on every
     * failed *query*, not just a failed connection, which would turn an ordinary
     * duplicate-key error into an exception and bypass the errno() contract that
     * every caller above depends on. Errors are collected into $errno/$error
     * instead, and a genuinely unreachable server is re-thrown by hand so it
     * still reaches bootstrap's exception handler.
     */
    private function conn(): mysqli
    {
        if ($this->connection instanceof mysqli) {
            return $this->connection;
        }

        if ($this->dead) {
            throw new RuntimeException('Technical difficulties: the database is unavailable. Please try again shortly.');
        }

        $previous = mysqli_report(MYSQLI_REPORT_OFF);
        $connection = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        mysqli_report($previous);

        if ($connection->connect_errno) {
            $this->dead = true;
            $this->errno = $connection->connect_errno;
            $this->error = $connection->connect_error;
            /*
             * The wording matters. bootstrap's exception handler matches a
             * RuntimeException on the phrase "technical difficulties" to decide
             * this is a database outage and serve app/includes/db-error.php
             * instead of a bare 500 — so this must not be reworded casually.
             */
            throw new RuntimeException('Technical difficulties: the database is unavailable. Please try again shortly.');
        }

        $connection->set_charset('utf8mb4');
        $this->connection = $connection;

        return $connection;
    }

    /** The driver error number from the most recent statement, 0 when it worked. */
    public function errno(): int
    {
        return $this->errno;
    }

    /** The driver error text from the most recent statement. */
    public function error(): string
    {
        return $this->error;
    }

    /** Forget the last error, so the next check starts clean. */
    private function clear(): void
    {
        $this->errno = 0;
        $this->error = '';
    }

    /**
     * Remember a failed statement. mysqli_sql_exception is still caught with a
     * try/catch rather than left to propagate, because with reporting off it is
     * only ever thrown by an explicit ->check() and that is cheaper to reason
     * about than a global handler.
     */
    private function fail(mysqli $connection, string $fallback = ''): void
    {
        $this->errno = $connection->errno;
        $this->error = $connection->error !== '' ? $connection->error : $fallback;
    }

    /**
     * Build the bind type string for a set of plain PHP values:
     * int -> i, float -> d, everything else -> s.
     */
    private function types(array $values): string
    {
        $types = '';
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    /**
     * Prepare, bind, run and RELEASE one statement.
     *
     * Shared by execute(), select(), row(), scalar(), insert() and affected(),
     * because the only difference between them is what they do with the result.
     * The statement is always closed before returning — including on the failure
     * paths — so no caller can leak one by forgetting.
     *
     * The return value is `['ok' => bool, 'result' => ?mysqli_result]` rather
     * than the raw handle because "it worked and returned no rows" and "it
     * failed" must be distinguishable. mysqli::query() returns a mysqli_result
     * for a SELECT, plain true for a CREATE/INSERT/UPDATE, and false on error;
     * collapsing those to a nullable handle would report every non-SELECT as a
     * failure.
     *
     * @return array{ok: bool, result: mysqli_result|null, insert_id: int, affected: int}
     */
    private function run(string $sql, array $values): array
    {
        $this->clear();

        $connection = $this->conn();
        $values = array_values($values);

        if ($values === []) {
            try {
                $result = $connection->query($sql);
            } catch (mysqli_sql_exception $e) {
                $this->errno = (int)$e->getCode();
                $this->error = $e->getMessage();
                return ['ok' => false, 'result' => null, 'insert_id' => 0, 'affected' => 0];
            }

            if ($result === false) {
                $this->fail($connection);
                return ['ok' => false, 'result' => null, 'insert_id' => 0, 'affected' => 0];
            }

            return [
                'ok'        => true,
                'result'    => $result instanceof mysqli_result ? $result : null,
                'insert_id' => (int)$connection->insert_id,
                'affected'  => (int)$connection->affected_rows,
            ];
        }

        $statement = @$connection->prepare($sql);
        if (!$statement) {
            $this->fail($connection, 'Could not prepare the statement.');
            return ['ok' => false, 'result' => null, 'insert_id' => 0, 'affected' => 0];
        }

        $statement->bind_param($this->types($values), ...$values);

        try {
            // Suppressed: with reporting off a rejected statement still raises a
            // PHP warning. An ordinary duplicate-key write must not print one —
            // the caller asks errno() instead.
            if (!@$statement->execute()) {
                $this->errno = $statement->errno;
                $this->error = $statement->error;
                $statement->close();
                return ['ok' => false, 'result' => null, 'insert_id' => 0, 'affected' => 0];
            }

            // Read the buffer out before closing: get_result() is only valid
            // while the statement is live.
            $result = $statement->get_result();

            /*
             * Read these BEFORE close(), not after. On this build closing a
             * prepared statement resets the connection's affected_rows to -1, so
             * insert() and affected() would otherwise report 0 and -1 for
             * writes that plainly worked.
             */
            $insertId = (int)$connection->insert_id;
            $affected = (int)$connection->affected_rows;
        } catch (mysqli_sql_exception $e) {
            $this->errno = (int)$e->getCode();
            $this->error = $e->getMessage();
            $statement->close();
            return ['ok' => false, 'result' => null, 'insert_id' => 0, 'affected' => 0];
        }

        $statement->close();

        return [
            'ok'        => true,
            'result'    => $result instanceof mysqli_result ? $result : null,
            'insert_id' => $insertId,
            'affected'  => $affected,
        ];
    }

    /**
     * Fetch every row of a result as an associative array, then free it.
     *
     * MYSQLI_ASSOC rather than BOTH: no caller in the app needs the numeric
     * keys, and carrying both doubles the memory of every list page.
     */
    private function rows(mixed $result): array
    {
        if (!$result instanceof mysqli_result) {
            return [];
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        return $rows;
    }

    /**
     * Run any statement. Returns true when it worked, false when it did not —
     * it does not throw, and errno() says why.
     */
    public function execute(string $sql, array $values = []): bool
    {
        return $this->run($sql, $values)['ok'];
    }

    /** Run a SELECT and return every row. */
    public function select(string $sql, array $values = []): array
    {
        return $this->rows($this->run($sql, $values)['result']);
    }

    /** Run a SELECT and return its first row, or null. */
    public function row(string $sql, array $values = []): ?array
    {
        $rows = $this->rows($this->run($sql, $values)['result']);
        return $rows[0] ?? null;
    }

    /**
     * Run a SELECT and return the first column of the first row.
     *
     * No default: every caller either wants a real value or checks for null
     * itself, and a silent '' would turn a missing row into a plausible-looking
     * empty string.
     */
    public function scalar(string $sql, array $values = [])
    {
        $row = $this->row($sql, $values);
        return $row === null ? null : reset($row);
    }

    /**
     * Run an INSERT and return the new id, or 0 when it failed.
     *
     * Takes the statement rather than a table plus an array, because callers
     * write the column list themselves — several need NULLIF(?, "") or an
     * expression in a value, which a table+array signature cannot express.
     */
    public function insert(string $sql, array $values = []): int
    {
        $result = $this->run($sql, $values);

        return $result['ok'] ? $result['insert_id'] : 0;
    }

    /**
     * Run a write and return the number of rows it touched.
     *
     * Used where the caller needs to know whether anything matched — a DELETE
     * that removed nothing and one that removed a row are different outcomes.
     */
    public function affected(string $sql, array $values = []): int
    {
        $result = $this->run($sql, $values);

        return $result['ok'] ? $result['affected'] : 0;
    }
}