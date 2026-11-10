<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * OCI Object Storage adapter using Oracle's S3-compatible API and AWS Signature V4.
 * The bucket stays private; media URLs are short-lived signed GET requests.
 */
final class ObjectStorage
{
    private static ?self $instance = null;
    private string $namespace;
    private string $region;
    private string $bucket;
    private string $accessKey;
    private string $secretKey;
    private string $endpoint;

    private function __construct()
    {
        $this->namespace = trim((string)\App\core\Environment::get('OCI_OBJECT_STORAGE_NAMESPACE', ''));
        $this->region = trim((string)\App\core\Environment::get('OCI_OBJECT_STORAGE_REGION', ''));
        $this->bucket = trim((string)\App\core\Environment::get('OCI_OBJECT_STORAGE_BUCKET', ''));
        $this->accessKey = trim((string)\App\core\Environment::get('OCI_OBJECT_STORAGE_ACCESS_KEY', ''));
        $this->secretKey = trim((string)\App\core\Environment::get('OCI_OBJECT_STORAGE_SECRET_KEY', ''));

        if ($this->namespace === '' || $this->region === '' || $this->bucket === '' || $this->accessKey === '' || $this->secretKey === '') {
            throw new RuntimeException('Oracle Object Storage is enabled but its configuration is incomplete.');
        }

        $endpoint = trim((string)\App\core\Environment::get('OCI_OBJECT_STORAGE_ENDPOINT', ''));
        $this->endpoint = rtrim($endpoint !== ''
            ? $endpoint
            : 'https://' . $this->namespace . '.compat.objectstorage.' . $this->region . '.oraclecloud.com', '/');
        if (!preg_match('~^https://[A-Za-z0-9.-]+(?::[0-9]{1,5})?$~', $this->endpoint)) {
            throw new RuntimeException('The Oracle Object Storage endpoint must be a valid HTTPS endpoint.');
        }
    }

    public static function enabled(): bool
    {
        return in_array(strtolower(trim((string)\App\core\Environment::get('OCI_OBJECT_STORAGE_ENABLED', 'false'))), ['1', 'true', 'yes', 'on'], true);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** Return whether a stored path is one of the app's uploaded-media paths. */
    public static function isMediaPath(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === '' || str_contains($path, '..') || preg_match('~[^A-Za-z0-9._/-]~', $path)) {
            return false;
        }
        if (preg_match('~^audio/[A-Za-z0-9._/-]+$~', $path) || preg_match('~^images/artwork/[A-Za-z0-9._/-]+$~', $path)) {
            return true;
        }
        return (bool)preg_match('~^images/[A-Za-z0-9_-]+-[a-f0-9]{8}\.(?:jpe?g|png|gif|webp)$~i', $path);
    }

    /** Upload a temporary file without buffering it in PHP memory. */
    public function uploadFile(string $source, string $key, string $contentType = 'application/octet-stream'): void
    {
        $key = $this->key($key);
        if (!is_file($source) || !is_readable($source)) {
            throw new RuntimeException('The uploaded media file is not readable.');
        }
        $size = filesize($source);
        $payloadHash = hash_file('sha256', $source);
        if ($size === false || $payloadHash === false) {
            throw new RuntimeException('The uploaded media file could not be prepared.');
        }

        $headers = [
            'content-type' => $contentType,
            'host' => $this->host(),
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => gmdate('Ymd\THis\Z'),
        ];
        $url = $this->objectUrl($key);
        $signed = $this->signedHeaders('PUT', $url, $headers, $payloadHash);
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $handle = fopen($source, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The uploaded media file could not be opened.');
        }
        $curl = curl_init($url);
        if ($curl === false) {
            fclose($handle);
            throw new RuntimeException('Object Storage could not be reached.');
        }
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $handle,
            CURLOPT_INFILESIZE => (int)$size,
            CURLOPT_HTTPHEADER => [...$lines, 'Authorization: ' . $signed],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $failed = $response === false;
        curl_close($curl);
        fclose($handle);
        if ($failed || $status < 200 || $status >= 300) {
            throw new RuntimeException('Object Storage upload failed (HTTP ' . $status . ').');
        }
    }

    /** Delete an object; callers pass only paths sourced from the database or generated by store(). */
    public function delete(string $key): void
    {
        $key = $this->key($key);
        $url = $this->objectUrl($key);
        $payloadHash = hash('sha256', '');
        $headers = [
            'host' => $this->host(),
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => gmdate('Ymd\THis\Z'),
        ];
        $signed = $this->signedHeaders('DELETE', $url, $headers, $payloadHash);
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $response = $this->request('DELETE', $url, [...$lines, 'Authorization: ' . $signed]);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('Object Storage deletion failed (HTTP ' . $response['status'] . ').');
        }
    }

    /** A short-lived GET link; optional content disposition is used by downloads. */
    public function url(string $key, int $expires = 3600, ?string $contentDisposition = null): string
    {
        $key = $this->key($key);
        $expires = max(60, min(604800, $expires));
        $date = gmdate('Ymd\THis\Z');
        $day = substr($date, 0, 8);
        $scope = $day . '/' . $this->region . '/s3/aws4_request';
        $params = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->accessKey . '/' . $scope,
            'X-Amz-Date' => $date,
            'X-Amz-Expires' => (string)$expires,
            'X-Amz-SignedHeaders' => 'host',
        ];
        if ($contentDisposition !== null && $contentDisposition !== '') {
            $params['response-content-disposition'] = $contentDisposition;
        }
        $canonicalQuery = $this->query($params);
        $canonicalRequest = "GET\n" . $this->canonicalPath($key) . "\n" . $canonicalQuery
            . "\nhost:" . $this->host() . "\n\nhost\nUNSIGNED-PAYLOAD";
        $toSign = "AWS4-HMAC-SHA256\n" . $date . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $toSign, $this->signingKey($day));
        return $this->objectUrl($key) . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
    }

    /** Migrate local media while preserving the database path/key. */
    public function uploadLocalTree(string $directory, string $prefix): int
    {
        if (!is_dir($directory)) {
            return 0;
        }
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($directory, '/')) + 1));
            $key = trim($prefix, '/') . '/' . $relative;
            $mime = 'application/octet-stream';
            if (class_exists('finfo')) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = (string)($finfo->file($file->getPathname()) ?: $mime);
            }
            $this->uploadFile($file->getPathname(), $key, $mime);
            $count++;
        }
        return $count;
    }

    private function request(string $method, string $url, array $headers): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Object Storage could not be reached.');
        }
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $failed = $body === false;
        curl_close($curl);
        if ($failed) {
            throw new RuntimeException('Object Storage request could not be completed.');
        }
        return ['status' => $status, 'body' => (string)$body];
    }

    private function signedHeaders(string $method, string $url, array $headers, string $payloadHash): string
    {
        $date = $headers['x-amz-date'];
        $day = substr($date, 0, 8);
        ksort($headers);
        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= strtolower($name) . ':' . trim((string)$value) . "\n";
        }
        $signedHeaders = implode(';', array_map('strtolower', array_keys($headers)));
        $parts = parse_url($url);
        $canonicalRequest = $method . "\n" . $parts['path'] . "\n\n" . $canonicalHeaders
            . "\n" . $signedHeaders . "\n" . $payloadHash;
        $scope = $day . '/' . $this->region . '/s3/aws4_request';
        $toSign = "AWS4-HMAC-SHA256\n" . $date . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $toSign, $this->signingKey($day));
        return 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;
    }

    private function signingKey(string $day): string
    {
        $dateKey = hash_hmac('sha256', $day, 'AWS4' . $this->secretKey, true);
        $regionKey = hash_hmac('sha256', $this->region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        return hash_hmac('sha256', 'aws4_request', $serviceKey, true);
    }

    private function objectUrl(string $key): string
    {
        return $this->endpoint . '/' . rawurlencode($this->bucket) . '/' . $this->encodedKey($key);
    }

    private function canonicalPath(string $key): string
    {
        return '/' . rawurlencode($this->bucket) . '/' . $this->encodedKey($key);
    }

    private function encodedKey(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    private function host(): string
    {
        return (string)parse_url($this->endpoint, PHP_URL_HOST);
    }

    private function key(string $key): string
    {
        $key = ltrim(str_replace('\\', '/', trim($key)), '/');
        if ($key === '' || preg_match('~[^A-Za-z0-9._/-]~', $key)
            || in_array('..', explode('/', $key), true) || in_array('.', explode('/', $key), true)) {
            throw new RuntimeException('Invalid Object Storage key.');
        }
        return $key;
    }

    private function query(array $params): string
    {
        ksort($params, SORT_STRING);
        $parts = [];
        foreach ($params as $name => $value) {
            $parts[] = rawurlencode((string)$name) . '=' . rawurlencode((string)$value);
        }
        return implode('&', $parts);
    }
}
