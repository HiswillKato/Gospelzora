(function () {
    'use strict';

    var doc = document;
    var root = doc.documentElement;
    var THEME_KEY = 'gospelzora-theme';
    var TOAST_DELAY = 4500;

    function $(selector, scope) {
        return (scope || doc).querySelector(selector);
    }

    function $$(selector, scope) {
        return Array.prototype.slice.call((scope || doc).querySelectorAll(selector));
    }

    function closest(node, selector) {
        return node && node.closest ? node.closest(selector) : null;
    }

    function bootstrap() {
        return window.bootstrap || null;
    }

    function csrfToken() {
        var meta = doc.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    function escapeHtml(value) {
        if (value === undefined || value === null) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function ownValue(map, key) {
        return Object.prototype.hasOwnProperty.call(map, key);
    }

    function messageOf(data, fallback) {
        if (data && typeof data.message === 'string' && data.message !== '') {
            return data.message;
        }
        return fallback;
    }

    function apiUrl(name) {
        var meta = doc.querySelector('meta[name="base-url"]');
        var base = meta ? (meta.getAttribute('content') || '') : '';
        base = base.replace(/\/+$/, '');
        return base + '/api/' + name;
    }

    function toInt(value) {
        var number = parseInt(value, 10);
        return isFinite(number) ? number : 0;
    }

    function toSeconds(value) {
        if (value === undefined || value === null || value === '') {
            return 0;
        }
        var text = String(value).trim();
        if (/^\d+(\.\d+)?$/.test(text)) {
            return parseFloat(text);
        }
        var parts = text.split(':');
        var total = 0;
        for (var i = 0; i < parts.length; i++) {
            var piece = parseFloat(parts[i]);
            if (!isFinite(piece)) {
                return 0;
            }
            total = total * 60 + piece;
        }
        return total;
    }

    function formatTime(seconds) {
        var total = toSeconds(seconds);
        if (!isFinite(total) || total < 0) {
            return '0:00';
        }
        var whole = Math.floor(total);
        var minutes = Math.floor(whole / 60);
        var rest = whole % 60;
        return minutes + ':' + (rest < 10 ? '0' : '') + rest;
    }

    function titleCase(value) {
        return String(value)
            .split('-')
            .map(function (part) {
                return part ? part.charAt(0).toUpperCase() + part.slice(1) : part;
            })
            .join(' ');
    }

    function iconName(value, fallback) {
        var text = String(value === undefined || value === null ? '' : value);
        text = text.replace(/^\s*fa-(?:solid|regular|brands)\s+/i, '');
        var match = /fa-([a-z0-9-]+)/i.exec(text);
        if (match) {
            return match[1].toLowerCase();
        }
        var clean = text.toLowerCase().replace(/[^a-z0-9-]/g, '');
        return clean === '' ? (fallback || 'music') : clean;
    }

    function iconStyle(value) {
        var match = /fa-(solid|regular|brands)/i.exec(String(value === undefined || value === null ? '' : value));
        return match ? match[1].toLowerCase() : 'solid';
    }

    function swapIcon(node, name, style) {
        if (!node) {
            return;
        }
        var glyph = node.querySelector('i.fa-icon') || node.querySelector('i');
        if (!glyph) {
            return;
        }
        var kept = [];
        String(glyph.getAttribute('class') || '').split(/\s+/).forEach(function (name_) {
            if (!name_ || name_ === 'fa-icon') {
                return;
            }
            if (name_.indexOf('fa-') === 0) {
                return;
            }
            kept.push(name_);
        });
        if (kept.indexOf('fa-icon') === -1) {
            kept.unshift('fa-icon');
        }
        kept.push('fa-' + (style === 'regular' || style === 'brands' ? style : 'solid'));
        kept.push('fa-' + iconName(name, 'music'));
        glyph.setAttribute('class', kept.join(' '));
    }

    function labelNode(button) {
        var nodes = button.childNodes;
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].nodeType === 3 && String(nodes[i].nodeValue).trim() !== '') {
                return nodes[i];
            }
        }
        return null;
    }

    function request(url, options) {
        var opts = options || {};
        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        var token = csrfToken();
        if (token !== '') {
            headers['X-CSRF-Token'] = token;
        }
        var init = {
            method: opts.method || 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: headers
        };
        if (opts.body) {
            init.body = opts.body;
        }
        return window.fetch(url, init).then(function (response) {
            return response.text().then(function (text) {
                var data = null;
                if (text) {
                    try {
                        data = JSON.parse(text);
                    } catch (error) {
                        data = null;
                    }
                }
                if (!response.ok) {
                    var failure = new Error(messageOf(data, 'The server could not complete that request.'));
                    failure.status = response.status;
                    failure.data = data;
                    throw failure;
                }
                if (!data || typeof data !== 'object') {
                    throw new Error('The server sent a reply we could not read.');
                }
                return data;
            });
        }, function () {
            throw new Error('We could not reach the server. Check your connection and try again.');
        });
    }

    function post(url, data) {
        var payload = data || {};
        var body = new URLSearchParams();
        Object.keys(payload).forEach(function (key) {
            var value = payload[key];
            if (value === undefined || value === null) {
                return;
            }
            body.append(key, String(value));
        });
        var token = csrfToken();
        if (token !== '' && !ownValue(payload, 'csrf_token')) {
            body.append('csrf_token', token);
        }
        return request(url, { method: 'POST', body: body });
    }

    function getJSON(url) {
        return request(url, { method: 'GET' });
    }

    var TOAST_ICON = {
        success: 'circle-check',
        danger: 'circle-xmark',
        warning: 'triangle-exclamation',
        info: 'circle-info'
    };

    function toastHolder() {
        var holder = doc.getElementById('zora-toasts');
        if (holder) {
            return holder;
        }
        holder = doc.createElement('div');
        holder.id = 'zora-toasts';
        holder.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        holder.setAttribute('aria-live', 'polite');
        holder.setAttribute('aria-atomic', 'true');
        doc.body.appendChild(holder);
        return holder;
    }

    function toast(message, type) {
        var text = message === undefined || message === null ? '' : String(message);
        if (text === '') {
            return null;
        }
        var kind = ownValue(TOAST_ICON, type) ? type : 'info';
        var node = doc.createElement('div');
        node.className = 'toast align-items-center border-0 text-bg-' + kind;
        node.setAttribute('role', kind === 'danger' ? 'alert' : 'status');
        node.innerHTML = '<div class="d-flex">'
            + '<i class="fa-icon fa-solid fa-' + TOAST_ICON[kind] + ' me-2 ms-3" aria-hidden="true"></i>'
            + '<div class="toast-body"></div>'
            + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>'
            + '</div>';
        node.querySelector('.toast-body').textContent = text;
        toastHolder().appendChild(node);

        node.addEventListener('hidden.bs.toast', function () {
            if (node.parentNode) {
                node.parentNode.removeChild(node);
            }
        });

        var api = bootstrap();
        if (api && api.Toast) {
            api.Toast.getOrCreateInstance(node, { delay: TOAST_DELAY }).show();
        } else {
            node.classList.add('show');
            window.setTimeout(function () {
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            }, TOAST_DELAY);
        }
        return node;
    }

    var Theme = {
        saved: function () {
            try {
                var value = window.localStorage.getItem(THEME_KEY);
                return value === 'light' || value === 'dark' ? value : '';
            } catch (error) {
                return '';
            }
        },
        save: function (value) {
            try {
                window.localStorage.setItem(THEME_KEY, value);
            } catch (error) {
                return;
            }
        },
        apply: function (value) {
            if (value === 'light' || value === 'dark') {
                root.setAttribute('data-theme', value);
                root.setAttribute('data-bs-theme', value);
            } else {
                root.removeAttribute('data-theme');
                root.removeAttribute('data-bs-theme');
            }
            Theme.sync();
        },
        current: function () {
            var attribute = root.getAttribute('data-theme');
            if (attribute === 'light' || attribute === 'dark') {
                return attribute;
            }
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
            return 'light';
        },
        toggle: function () {
            var next = Theme.current() === 'dark' ? 'light' : 'dark';
            Theme.save(next);
            Theme.apply(next);
            return next;
        },
        sync: function () {
            var button = doc.getElementById('themeToggle');
            if (!button) {
                return;
            }
            var dark = Theme.current() === 'dark';
            var word = dark ? 'light' : 'dark';
            button.setAttribute('aria-label', 'Switch to the ' + word + ' theme');
            button.setAttribute('title', 'Switch to the ' + word + ' theme');
            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            var sun = button.querySelector('.fa-sun');
            var moon = button.querySelector('.fa-moon');
            if (sun) {
                sun.style.display = dark ? 'inline-block' : 'none';
            }
            if (moon) {
                moon.style.display = dark ? 'none' : 'inline-block';
            }
        },
        init: function () {
            Theme.apply(Theme.saved() || Theme.current());
            var button = doc.getElementById('themeToggle');
            if (button) {
                button.addEventListener('click', function () {
                    Theme.toggle();
                });
            }
            if (window.matchMedia) {
                var query = window.matchMedia('(prefers-color-scheme: dark)');
                var listen = function () {
                    if (Theme.saved() === '') {
                        Theme.sync();
                    }
                };
                if (typeof query.addEventListener === 'function') {
                    query.addEventListener('change', listen);
                } else if (typeof query.addListener === 'function') {
                    query.addListener(listen);
                }
            }
        }
    };

    var Player = {
        bar: null,
        audio: null,
        id: '',
        queue: [],
        at: -1
    };

    function normaliseTrack(raw) {
        if (!raw || typeof raw !== 'object') {
            return null;
        }
        return {
            id: String(raw.id === undefined || raw.id === null ? '' : raw.id),
            title: String(raw.title === undefined || raw.title === null ? '' : raw.title),
            artist: String(raw.artist === undefined || raw.artist === null ? '' : raw.artist),
            cover: String(raw.cover === undefined || raw.cover === null ? '' : raw.cover),
            audio: String(raw.audio === undefined || raw.audio === null ? '' : raw.audio),
            duration: String(raw.duration === undefined || raw.duration === null ? '' : raw.duration)
        };
    }

    function playerParts() {
        return {
            art: $('.player-bar__art', Player.bar),
            title: $('.player-bar__title', Player.bar),
            artist: $('.player-bar__artist', Player.bar),
            toggle: $('[data-player="toggle"]', Player.bar),
            close: $('[data-player="close"]', Player.bar),
            seek: $('[data-player="seek"]', Player.bar),
            elapsed: $('[data-player="elapsed"]', Player.bar),
            total: $('[data-player="total"]', Player.bar)
        };
    }

    function playerSync() {
        if (!Player.audio) {
            return;
        }
        var playing = !Player.audio.paused && !Player.audio.ended;
        var parts = playerParts();
        if (parts.toggle) {
            swapIcon(parts.toggle, playing ? 'pause' : 'play', 'solid');
            parts.toggle.setAttribute('aria-label', playing ? 'Pause' : 'Play');
        }
        $$('.btn-play-song').forEach(function (button) {
            var current = String(button.getAttribute('data-id') || '') === Player.id;
            var live = current && playing;
            button.classList.toggle('is-playing', live);
            if (current) {
                button.setAttribute('aria-pressed', live ? 'true' : 'false');
                button.setAttribute('aria-label', (playing ? 'Pause ' : 'Play ') + (button.getAttribute('data-title') || 'this song'));
            }
            swapIcon(button, live ? 'pause' : 'play', 'solid');
            var text = labelNode(button);
            if (text) {
                text.nodeValue = live ? 'Pause' : 'Play';
            }
        });
    }

    function playerProgress() {
        if (!Player.audio) {
            return;
        }
        var parts = playerParts();
        var length = Player.audio.duration;
        if (!isFinite(length) || length <= 0) {
            length = toSeconds(Player.bar.getAttribute('data-length') || '');
        }
        if (parts.total) {
            parts.total.textContent = formatTime(length);
        }
        if (parts.elapsed) {
            parts.elapsed.textContent = formatTime(Player.audio.currentTime);
        }
        if (parts.seek) {
            if (isFinite(length) && length > 0) {
                parts.seek.max = String(Math.floor(length));
                parts.seek.disabled = false;
                parts.seek.value = String(Math.floor(Player.audio.currentTime));
            } else {
                parts.seek.value = '0';
            }
        }
    }

    function playerPaint(track) {
        var parts = playerParts();
        if (parts.art) {
            if (track.cover !== '') {
                parts.art.setAttribute('src', track.cover);
                parts.art.removeAttribute('hidden');
            } else {
                parts.art.removeAttribute('src');
                parts.art.setAttribute('hidden', 'hidden');
            }
            parts.art.setAttribute('alt', track.title);
        }
        if (parts.title) {
            parts.title.textContent = track.title;
        }
        if (parts.artist) {
            parts.artist.textContent = track.artist;
        }
        Player.bar.setAttribute('data-length', track.duration);
    }

    function playerShow() {
        Player.bar.hidden = false;
        doc.body.classList.add('has-player');
    }

    function playerHide() {
        Player.bar.hidden = true;
        doc.body.classList.remove('has-player');
    }

    function playerLoad(track, autoplay) {
        if (!track || track.audio === '') {
            toast('That song has no audio file to play yet.', 'warning');
            return;
        }
        playerBuild();
        Player.id = track.id;
        if (Player.audio.getAttribute('src') !== track.audio) {
            Player.audio.setAttribute('src', track.audio);
            try {
                Player.audio.load();
            } catch (error) {
                Player.audio.setAttribute('src', track.audio);
            }
        }
        playerPaint(track);
        playerShow();
        playerProgress();
        playerSync();
        if (autoplay !== false) {
            var attempt = Player.audio.play();
            if (attempt && typeof attempt.catch === 'function') {
                attempt.catch(function () {
                    playerSync();
                });
            }
        }
    }

    function playerToggle(track) {
        if (!track || track.audio === '') {
            toast('That song has no audio file to play yet.', 'warning');
            return;
        }
        if (track.id !== '' && track.id === Player.id && Player.audio) {
            if (Player.audio.paused) {
                var attempt = Player.audio.play();
                if (attempt && typeof attempt.catch === 'function') {
                    attempt.catch(function () {
                        playerSync();
                    });
                }
            } else {
                Player.audio.pause();
            }
            playerSync();
            return;
        }
        Player.at = -1;
        playerLoad(track, true);
    }

    function playerClose() {
        if (!Player.audio) {
            return;
        }
        Player.audio.pause();
        Player.audio.removeAttribute('src');
        try {
            Player.audio.load();
        } catch (error) {
            Player.audio.removeAttribute('src');
        }
        Player.id = '';
        Player.queue = [];
        Player.at = -1;
        playerHide();
        playerSync();
    }

    function playerAdvance() {
        if (Player.at < 0 || Player.at >= Player.queue.length - 1) {
            return false;
        }
        Player.at += 1;
        playerLoad(Player.queue[Player.at], true);
        return true;
    }

    function playerBuild() {
        if (Player.bar && Player.audio) {
            return Player.bar;
        }
        var bar = doc.createElement('div');
        bar.className = 'player-bar';
        bar.id = 'zora-player';
        bar.hidden = true;
        bar.innerHTML = [
            '<div class="container player-bar__inner">',
            '<img class="player-bar__art" alt="" aria-hidden="true">',
            '<div class="player-bar__meta">',
            '<span class="player-bar__title"></span>',
            '<span class="player-bar__artist"></span>',
            '</div>',
            '<button type="button" class="btn btn-gold player-bar__icon" data-player="toggle" aria-label="Play">',
            '<i class="fa-icon fa-solid fa-play" aria-hidden="true"></i></button>',
            '<span class="player-bar__time" data-player="elapsed">0:00</span>',
            '<input type="range" class="player-bar__seek" data-player="seek" min="0" max="100" step="1" value="0" aria-label="Seek through the song">',
            '<span class="player-bar__time" data-player="total">0:00</span>',
            '<button type="button" class="btn btn-outline-secondary player-bar__icon" data-player="close" aria-label="Close the player">',
            '<i class="fa-icon fa-solid fa-xmark" aria-hidden="true"></i></button>',
            '<audio preload="none"></audio>',
            '</div>'
        ].join('');
        doc.body.appendChild(bar);

        var audio = $('audio', bar);
        Player.bar = bar;
        Player.audio = audio;

        bar.addEventListener('click', function (event) {
            var button = closest(event.target, 'button');
            if (!button) {
                return;
            }
            var role = button.getAttribute('data-player');
            if (role === 'toggle') {
                if (audio.paused) {
                    var attempt = audio.play();
                    if (attempt && typeof attempt.catch === 'function') {
                        attempt.catch(function () {
                            playerSync();
                        });
                    }
                } else {
                    audio.pause();
                }
                playerSync();
            } else if (role === 'close') {
                playerClose();
            }
        });

        bar.addEventListener('input', function (event) {
            var seek = closest(event.target, '[data-player="seek"]');
            if (!seek) {
                return;
            }
            var length = audio.duration;
            if (!isFinite(length) || length <= 0) {
                length = toSeconds(bar.getAttribute('data-length') || '');
            }
            if (!isFinite(length) || length <= 0) {
                return;
            }
            var value = parseFloat(seek.value);
            if (isFinite(value)) {
                audio.currentTime = Math.min(Math.max(value, 0), length);
                playerProgress();
            }
        });

        audio.addEventListener('play', playerSync);
        audio.addEventListener('pause', playerSync);
        audio.addEventListener('ended', function () {
            if (!playerAdvance()) {
                playerSync();
            }
        });
        audio.addEventListener('timeupdate', playerProgress);
        audio.addEventListener('loadedmetadata', playerProgress);
        audio.addEventListener('durationchange', playerProgress);
        audio.addEventListener('canplay', playerProgress);
        audio.addEventListener('error', function () {
            if (!audio.getAttribute('src')) {
                return;
            }
            toast('That audio file could not be played.', 'danger');
            playerSync();
        });

        return bar;
    }

    var GospelPlayer = {
        play: function (raw) {
            var track = normaliseTrack(raw);
            if (!track) {
                return;
            }
            Player.at = -1;
            playerLoad(track, true);
        },
        playPlaylist: function (songs) {
            var list = [];
            if (!songs || typeof songs.length !== 'number') {
                return;
            }
            for (var i = 0; i < songs.length; i++) {
                var track = normaliseTrack(songs[i]);
                if (track && track.audio !== '') {
                    list.push(track);
                }
            }
            if (!list.length) {
                toast('There is nothing to play in this playlist yet.', 'info');
                return;
            }
            Player.queue = list;
            Player.at = 0;
            playerLoad(list[0], true);
        },
        pause: function () {
            if (Player.audio) {
                Player.audio.pause();
                playerSync();
            }
        },
        close: playerClose,
        isPlaying: function () {
            return !!Player.audio && !Player.audio.paused && !Player.audio.ended;
        }
    };

    var PlaylistPicker = (function () {
        var modal = null;
        var instance = null;
        var songId = '';
        var rows = [];

        function slots() {
            return {
                song: $('[data-slot="song"]', modal),
                status: $('[data-slot="status"]', modal),
                list: $('[data-slot="list"]', modal),
                form: $('[data-slot="create"]', modal),
                name: $('#zora-playlist-name', modal),
                privacy: $('[data-slot="create"] select[name="privacy"]', modal),
                submit: $('[data-slot="create"] button[type="submit"]', modal)
            };
        }

        function status(text, kind) {
            var box = slots().status;
            if (!box) {
                return;
            }
            if (!text) {
                box.innerHTML = '';
                return;
            }
            var glyph = kind === 'danger' ? 'triangle-exclamation' : (kind === 'success' ? 'circle-check' : 'circle-info');
            box.innerHTML = '<div class="alert alert-' + (kind === 'danger' ? 'danger' : (kind === 'success' ? 'success' : 'info')) + ' d-flex align-items-center gap-2 mb-0">'
                + '<i class="fa-icon fa-solid fa-' + glyph + '" aria-hidden="true"></i>'
                + '<span></span></div>';
            box.querySelector('span').textContent = text;
        }

        function row(item) {
            var added = item.has === true;
            var name = escapeHtml(item.name);
            var count = toInt(item.songs);
            var facts = [];
            facts.push(count + (count === 1 ? ' song' : ' songs'));
            facts.push(item.privacy === 'public' ? 'Public' : 'Private');
            var glyph = item.privacy === 'public' ? 'globe' : 'lock';
            var badge = added
                ? '<span class="badge text-bg-success">Added</span>'
                : '<i class="fa-icon fa-solid fa-plus text-muted" aria-hidden="true"></i>';
            return '<button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-3'
                + (added ? ' disabled' : '')
                + '" data-playlist="' + escapeHtml(item.id) + '" data-name="' + name + '" data-privacy="'
                + escapeHtml(item.privacy === 'public' ? 'public' : 'private') + '">'
                + '<i class="fa-icon fa-solid fa-' + glyph + ' text-muted" aria-hidden="true"></i>'
                + '<span class="flex-grow-1 text-start">'
                + '<span class="d-block fw-semibold">' + name + '</span>'
                + '<span class="small text-muted">' + escapeHtml(facts.join(' · ')) + '</span>'
                + '</span>'
                + badge
                + '</button>';
        }

        function render() {
            var parts = slots();
            if (!parts.list) {
                return;
            }
            parts.list.innerHTML = '';
            if (!rows.length) {
                parts.list.hidden = true;
                status('You have no playlists yet. Name this one below and it will be created for you.', 'info');
                if (parts.name) {
                    window.setTimeout(function () {
                        parts.name.focus();
                    }, 60);
                }
                return;
            }
            parts.list.hidden = false;
            var html = '';
            for (var i = 0; i < rows.length; i++) {
                html += row(rows[i]);
            }
            parts.list.innerHTML = html;
            status(rows.length + (rows.length === 1 ? ' playlist' : ' playlists') + ' available.', '');
        }

        function load() {
            var parts = slots();
            status('Loading your playlists...', 'info');
            if (parts.list) {
                parts.list.innerHTML = '';
                parts.list.hidden = true;
            }
            rows = [];
            post(apiUrl('playlist.php'), { action: 'list', song: songId }).then(function (data) {
                if (data.success === false) {
                    status(messageOf(data, 'Your playlists could not be loaded.'), 'danger');
                    return;
                }
                rows = data.playlists && typeof data.playlists.length === 'number' ? data.playlists : [];
                render();
            }).catch(function (error) {
                status(error.message, 'danger');
            });
        }

        function add(playlistId, name, privacy) {
            var isPublic = privacy === 'public';
            if (isPublic) {
                var question = '“' + name + '” is a public playlist, so anyone can see this song once it is added. Add it to ' + name + '?';
                if (!window.confirm(question)) {
                    return;
                }
            }
            status('Adding to ' + name + '...', 'info');
            post(apiUrl('playlist.php'), { action: 'add', playlist: playlistId, song: songId }).then(function (data) {
                if (data.success === false) {
                    status(messageOf(data, 'That song could not be added.'), 'danger');
                    return;
                }
                toast(messageOf(data, name + ' now has this song.'), 'success');
                load();
            }).catch(function (error) {
                status(error.message, 'danger');
            });
        }

        function createdId(data) {
            if (data.playlist && data.playlist.id) {
                return toInt(data.playlist.id);
            }
            return toInt(data.playlist_id || data.id);
        }

        function wire() {
            modal.addEventListener('click', function (event) {
                var dismiss = closest(event.target, '[data-bs-dismiss="modal"]');
                if (dismiss) {
                    if (bootstrap() && bootstrap().Modal) {
                        return;
                    }
                    hide();
                    return;
                }
                var pick = closest(event.target, '[data-playlist]');
                if (!pick || pick.disabled) {
                    return;
                }
                add(toInt(pick.getAttribute('data-playlist')), pick.getAttribute('data-name') || 'that playlist', pick.getAttribute('data-privacy'));
            });

            var parts = slots();
            if (parts.form) {
                parts.form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    var name = parts.name ? String(parts.name.value || '').trim() : '';
                    if (name === '') {
                        toast('Give the new playlist a name first.', 'warning');
                        if (parts.name) {
                            parts.name.focus();
                        }
                        return;
                    }
                    var privacy = parts.privacy && parts.privacy.value === 'public' ? 'public' : 'private';
                    if (parts.submit) {
                        parts.submit.disabled = true;
                    }
                    post(apiUrl('playlist.php'), { action: 'create', name: name, privacy: privacy }).then(function (data) {
                        if (data.success === false || createdId(data) <= 0) {
                            toast(messageOf(data, 'That playlist could not be created.'), 'danger');
                            return null;
                        }
                        if (parts.name) {
                            parts.name.value = '';
                        }
                        return add(createdId(data), name, privacy);
                    }).catch(function (error) {
                        toast(error.message, 'danger');
                    }).then(function () {
                        if (parts.submit) {
                            parts.submit.disabled = false;
                        }
                    });
                });
            }
        }

        function build() {
            if (modal) {
                return modal;
            }
            var node = doc.createElement('div');
            node.className = 'modal fade';
            node.id = 'zora-playlist-modal';
            node.tabIndex = -1;
            node.setAttribute('aria-hidden', 'true');
            node.setAttribute('aria-labelledby', 'zora-playlist-modal-title');
            node.innerHTML = [
                '<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">',
                '<div class="modal-content">',
                '<div class="modal-header">',
                '<h5 class="modal-title" id="zora-playlist-modal-title">Add to a playlist</h5>',
                '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>',
                '</div>',
                '<div class="modal-body">',
                '<p class="small text-muted" data-slot="song"></p>',
                '<div data-slot="status" class="mb-3"></div>',
                '<div data-slot="list" class="list-group mb-3" hidden></div>',
                '<form data-slot="create" novalidate>',
                '<label class="form-label" for="zora-playlist-name">Or make a new playlist</label>',
                '<div class="input-group">',
                '<input type="text" class="form-control" id="zora-playlist-name" name="name" maxlength="100" placeholder="Playlist name" autocomplete="off">',
                '<select class="form-select" name="privacy" aria-label="Who can see this playlist">',
                '<option value="private">Private</option>',
                '<option value="public">Public</option>',
                '</select>',
                '<button class="btn btn-gold" type="submit">Create</button>',
                '</div>',
                '</form>',
                '</div>',
                '</div>',
                '</div>',
                '</div>'
            ].join('');
            doc.body.appendChild(node);
            modal = node;
            wire();
            return modal;
        }

        function show() {
            var api = bootstrap();
            if (api && api.Modal) {
                instance = api.Modal.getOrCreateInstance(modal);
                instance.show();
                return;
            }
            modal.classList.add('show');
            modal.style.display = 'block';
            modal.removeAttribute('aria-hidden');
            doc.body.classList.add('modal-open');
            doc.body.style.overflow = 'hidden';
        }

        function hide() {
            var api = bootstrap();
            if (api && api.Modal) {
                if (instance) {
                    instance.hide();
                }
                return;
            }
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            doc.body.classList.remove('modal-open');
            doc.body.style.overflow = '';
        }

        function open(id, title) {
            songId = String(id === undefined || id === null ? '' : id);
            if (songId === '' || songId === '0') {
                toast('That song could not be found.', 'danger');
                return;
            }
            build();
            var parts = slots();
            if (parts.song) {
                parts.song.textContent = title ? 'Adding “' + title + '”' : '';
            }
            load();
            show();
        }

        return {
            open: open,
            hide: hide,
            refresh: function () {
                if (modal) {
                    load();
                }
            }
        };
    })();

    function favouritePressed(button) {
        var attribute = button.getAttribute('aria-pressed');
        if (attribute === 'true' || attribute === 'false') {
            return attribute === 'true';
        }
        return button.classList.contains('is-active') || button.classList.contains('active');
    }

    function favouritePaint(button, on) {
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        button.classList.toggle('is-active', on);
        button.classList.toggle('active', on);
        var label = button.getAttribute('data-title') || 'this song';
        button.setAttribute('aria-label', (on ? 'Remove ' : 'Add ') + label + (on ? ' from' : ' to') + ' favorites');
        swapIcon(button, 'heart', on ? 'solid' : 'regular');
        var text = labelNode(button);
        if (text) {
            text.nodeValue = on ? 'Favorited' : 'Favorite';
        }
    }

    function initFavourites() {
        $$('.btn-favorite').forEach(function (button) {
            favouritePaint(button, favouritePressed(button));
        });
        doc.addEventListener('click', function (event) {
            var button = closest(event.target, '.btn-favorite');
            if (!button) {
                return;
            }
            event.preventDefault();
            if (button.getAttribute('data-busy') === '1') {
                return;
            }
            if (String(button.getAttribute('data-logged-in') || '') === '0') {
                toast('Please log in to keep a list of your favourite songs.', 'info');
                return;
            }
            var id = button.getAttribute('data-id') || '';
            if (toInt(id) <= 0) {
                toast('That song could not be found.', 'danger');
                return;
            }
            button.setAttribute('data-busy', '1');
            if (button.tagName === 'BUTTON') {
                button.disabled = true;
            }
            post(apiUrl('favorite.php'), { song: id }).then(function (data) {
                if (data.success === false) {
                    toast(messageOf(data, 'That song could not be saved.'), 'danger');
                    return;
                }
                favouritePaint(button, !favouritePressed(button));
                toast(favouritePressed(button) ? 'Added to your favorites.' : 'Removed from your favorites.', 'success');
            }).catch(function (error) {
                toast(error.message, 'danger');
            }).then(function () {
                button.removeAttribute('data-busy');
                if (button.tagName === 'BUTTON') {
                    button.disabled = false;
                }
            });
        });
    }

    var ICONS = null;

    function icons() {
        if (ICONS) {
            return Promise.resolve(ICONS);
        }
        return getJSON(apiUrl('icons.php')).then(function (data) {
            ICONS = data && data.icons && typeof data.icons === 'object' ? data.icons : {};
            return ICONS;
        }).catch(function () {
            ICONS = {};
            return ICONS;
        });
    }

    function iconInput(picker) {
        var form = picker.closest('form');
        if (form) {
            var scoped = form.querySelector('input[name="icon"]');
            if (scoped) {
                return scoped;
            }
        }
        if (picker.parentNode && picker.parentNode.querySelector) {
            var near = picker.parentNode.querySelector('input[name="icon"]');
            if (near) {
                return near;
            }
        }
        return doc.querySelector('input[name="icon"]');
    }

    function closePanels() {
        $$('.icon-picker__panel').forEach(function (panel) {
            var picker = closest(panel, '.icon-picker');
            if (panel.parentNode) {
                panel.parentNode.removeChild(panel);
            }
            if (picker) {
                picker.removeAttribute('data-open');
                var toggle = $('.icon-picker-toggle', picker);
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            }
        });
    }

    function paintPicker(picker, value, name, uses) {
        var input = iconInput(picker);
        if (input) {
            input.value = value;
        }
        picker.setAttribute('data-value', value);
        var glyph = $('.icon-picker-glyph', picker);
        if (glyph) {
            glyph.innerHTML = '<i class="fa-icon fa-' + iconStyle(value) + ' fa-' + iconName(value) + '" aria-hidden="true"></i>';
        }
        var label = $('.icon-picker-name', picker);
        if (label) {
            label.textContent = name || titleCase(iconName(value));
        }
        var used = $('.icon-picker-use', picker);
        if (used) {
            used.textContent = uses || '';
        }
    }

    function tile(name, meta, selected) {
        var css = meta && meta.fa_class ? String(meta.fa_class) : 'fa-' + iconName(name);
        var uses = meta && meta.uses && typeof meta.uses.length === 'number' ? meta.uses.join(', ') : '';
        return '<button type="button" class="icon-picker__use' + (selected ? ' is-selected' : '') + '"'
            + ' data-icon="' + escapeHtml(css) + '"'
            + ' data-name="' + escapeHtml(name) + '"'
            + ' data-uses="' + escapeHtml(uses) + '">'
            + '<i class="fa-icon fa-' + iconStyle(css) + ' fa-' + iconName(css) + '" aria-hidden="true"></i>'
            + '<span>' + escapeHtml(name) + '</span>'
            + '</button>';
    }

    function fillPanel(panel, query) {
        var grid = $('.icon-picker__grid', panel);
        var picker = closest(panel, '.icon-picker');
        var current = picker ? picker.getAttribute('data-value') || '' : '';
        var needle = String(query || '').trim().toLowerCase();
        icons().then(function (all) {
            var html = '';
            var found = 0;
            Object.keys(all).forEach(function (name) {
                var meta = all[name] || {};
                var haystack = (name + ' ' + (meta.fa_class || '') + ' ' + ((meta.uses || []).join(' '))).toLowerCase();
                if (needle !== '' && haystack.indexOf(needle) === -1) {
                    return;
                }
                var css = meta.fa_class ? String(meta.fa_class) : 'fa-' + iconName(name);
                html += tile(name, meta, css === current);
                found += 1;
            });
            grid.innerHTML = found ? html : '<p class="icon-picker__empty mb-0">No icon matches “' + escapeHtml(needle) + '”.</p>';
        });
    }

    function buildPanel(picker) {
        var panel = doc.createElement('div');
        panel.className = 'icon-picker__panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Choose an icon');
        panel.innerHTML = '<input type="search" class="icon-picker__search" placeholder="Search icons..." aria-label="Search icons" autocomplete="off">'
            + '<div class="icon-picker__grid"></div>';
        picker.appendChild(panel);
        var search = $('.icon-picker__search', panel);
        search.addEventListener('input', function () {
            fillPanel(panel, search.value);
        });
        search.addEventListener('click', function (event) {
            event.stopPropagation();
        });
        fillPanel(panel, '');
        return panel;
    }

    function initIconPickers() {
        doc.addEventListener('click', function (event) {
            var target = event.target;
            var use = closest(target, '.icon-picker__use');
            if (use) {
                event.preventDefault();
                var owner = closest(use, '.icon-picker');
                if (owner) {
                    paintPicker(owner, use.getAttribute('data-icon') || '', use.getAttribute('data-name') || '', use.getAttribute('data-uses') || '');
                    closePanels();
                }
                return;
            }
            var toggle = closest(target, '.icon-picker-toggle');
            if (toggle) {
                event.preventDefault();
                var picker = closest(toggle, '.icon-picker');
                if (!picker) {
                    return;
                }
                if ($('.icon-picker__panel', picker)) {
                    closePanels();
                    return;
                }
                closePanels();
                buildPanel(picker);
                picker.setAttribute('data-open', '1');
                toggle.setAttribute('aria-expanded', 'true');
                var field = $('.icon-picker__search', picker);
                if (field) {
                    field.focus();
                }
                return;
            }
            if (closest(target, '.icon-picker')) {
                return;
            }
            if ($('.icon-picker__panel')) {
                closePanels();
            }
        });
        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                closePanels();
            }
        });
    }

    function initAdminSidebar() {
        var sidebar = doc.getElementById('adminSidebar');
        if (!sidebar) {
            return;
        }
        var backdrop = doc.getElementById('adminSidebarBackdrop');
        var toggle = doc.getElementById('adminSidebarToggle');
        var close = doc.getElementById('adminSidebarClose');

        function paint(open) {
            sidebar.classList.toggle('is-open', open);
            if (backdrop) {
                backdrop.hidden = !open;
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        }
        paint(false);

        if (toggle) {
            toggle.addEventListener('click', function () {
                paint(true);
            });
        }
        if (close) {
            close.addEventListener('click', function () {
                paint(false);
            });
        }
        if (backdrop) {
            backdrop.addEventListener('click', function () {
                paint(false);
            });
        }
        doc.addEventListener('keydown', function (event) {
            if ((event.key === 'Escape' || event.keyCode === 27) && sidebar.classList.contains('is-open')) {
                paint(false);
            }
        });
    }

    function boot() {
        Theme.init();
        initFavourites();
        initAdminSidebar();
        initIconPickers();
        doc.addEventListener('click', function (event) {
            var trigger = closest(event.target, '.js-playlist-add');
            if (!trigger) {
                return;
            }
            event.preventDefault();
            PlaylistPicker.open(trigger.getAttribute('data-song'), trigger.getAttribute('data-title') || '');
        });
        doc.addEventListener('click', function (event) {
            var button = closest(event.target, '.btn-play-song');
            if (!button) {
                return;
            }
            event.preventDefault();
            playerToggle(normaliseTrack({
                id: button.getAttribute('data-id') || '',
                title: button.getAttribute('data-title') || '',
                artist: button.getAttribute('data-artist') || '',
                cover: button.getAttribute('data-cover') || '',
                audio: button.getAttribute('data-audio') || '',
                duration: button.getAttribute('data-duration') || ''
            }));
        });
    }

    window.Theme = Theme;
    window.GospelPlayer = GospelPlayer;
    window.PlaylistPicker = PlaylistPicker;
    window.toast = toast;
    window.post = post;
    window.getJSON = getJSON;
    window.Zora = {
        escapeHtml: escapeHtml,
        formatTime: formatTime,
        request: request,
        toast: toast
    };

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
