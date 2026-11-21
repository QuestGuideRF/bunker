(function () {
    'use strict';
    var ROOM_STORAGE_KEY = 'bunker_current_room_id';
    var user = null;
    var currentRoom = null;
    var currentGame = null;
    var mainTab = 'rooms';
    var lastPhase = '';
    var lastRound = 0;
    var lastMsgId = 0;
    var selectedAttr = null;
    var authTab = 'login';
    var gameStateChangeBound = false;
    var lastEliminatedToastRound = null;
    var votingPollInterval = null;
    var cardsPollInterval = null;
    var revealingPollInterval = null;
    var EXCLUDED_HIDDEN_KEY = 'bunker_excluded_hidden';
    var roomListRefreshInterval = null;
    var excludedSectionHidden = false;
    function e(str) {
        if (str == null) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
    function phaseName(phase) {
        var names = { cards: 'Карточки', revealing: 'Раскрытие', discussing: 'Обсуждение', voting: 'Голосование', finished: 'Завершена' };
        return names[phase] || phase;
    }
    function attrLabel(key) {
        var labels = { profession: 'Профессия', health: 'Здоровье', phobia: 'Фобия', bio: 'Биология', extra: 'Доп. инфо', baggage: 'Багаж', special_condition: 'Особое условие' };
        return labels[key] || key;
    }
    function shortVal(val) {
        if (val == null) return '—';
        if (typeof val === 'string' && (val.trim().charAt(0) === '{' || val.trim().charAt(0) === '[')) {
            try { return shortVal(JSON.parse(val)); } catch (_) {}
        }
        if (typeof val === 'object') {
            if (val.age !== undefined) return 'Возраст: ' + val.age + ', пол: ' + (val.gender || '') + ', дети: ' + (val.children ?? '—');
            if (val.skills || val.traits || val.hobbies || val.experiences) return [val.skills, val.traits, val.hobbies, val.experiences].filter(Boolean).join('; ');
            return JSON.stringify(val);
        }
        return String(val);
    }
    function formatExtra(val) {
        if (val == null) return '—';
        if (typeof val === 'string' && (val.trim().charAt(0) === '{')) {
            try { return formatExtra(JSON.parse(val)); } catch (_) { return val; }
        }
        if (typeof val !== 'object') return String(val);
        var parts = [];
        if (val.skills) parts.push('Навыки: ' + val.skills);
        if (val.traits) parts.push('Черты: ' + val.traits);
        if (val.hobbies) parts.push('Хобби: ' + val.hobbies);
        if (val.experiences) parts.push('Опыт: ' + val.experiences);
        return parts.length ? parts.join('\n') : '—';
    }
    function formatExtraHtml(val) {
        var text = formatExtra(val);
        if (text === '—') return '—';
        return text.split('\n').map(function (line) { return e(line); }).join('<br>');
    }
    function formatRevealVal(val, key) {
        if (key === 'extra') return formatExtra(val);
        return shortVal(val);
    }
    function formatTime(sec) {
        if (sec == null || sec < 0) return '0:00';
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }
    function getBasePath() {
        try {
            var p = (window.BUNKER_CONFIG && window.BUNKER_CONFIG.basePath) || '';
            return p === '/' ? '' : p;
        } catch (_) { return ''; }
    }
    function avatarHtml(url, nickname) {
        var initial = nickname ? nickname.trim().charAt(0).toUpperCase() : '?';
        if (url) {
            var base = getBasePath();
            var src = (base ? base + '/' : '/') + (url.indexOf('/') === 0 ? url.slice(1) : url);
            return '<img class="avatar" src="' + e(src) + '" alt="">';
        }
        return '<div class="avatar-initial">' + e(initial) + '</div>';
    }
    var UI = {
        showLoading: function () {
            var app = document.getElementById('app');
            if (!app) return;
            app.innerHTML = '<div class="loading">Загрузка…</div>';
        },
        hideLoading: function () {},
        toast: function (text, type) {
            var box = document.createElement('div');
            box.className = 'toast-box' + (type === 'error' ? ' error' : '');
            box.textContent = text;
            document.body.appendChild(box);
            setTimeout(function () {
                if (box.parentNode) box.parentNode.removeChild(box);
            }, 3000);
        },
        modal: function (title, content, onClose) {
            var back = document.createElement('div');
            back.className = 'modal-back';
            back.innerHTML = '<div class="modal-inner"><h3>' + e(title) + '</h3><div class="modal-body"></div><button type="button" class="btn btn-primary modal-close">Закрыть</button></div>';
            var body = back.querySelector('.modal-body');
            if (typeof content === 'string') body.innerHTML = content; else body.appendChild(content);
            back.querySelector('.modal-close').onclick = function () {
                document.body.removeChild(back);
                if (onClose) onClose();
            };
            back.onclick = function (ev) { if (ev.target === back) { document.body.removeChild(back); if (onClose) onClose(); } };
            document.body.appendChild(back);
        },
        closeModal: function () {
            var m = document.querySelector('.modal-back');
            if (m) document.body.removeChild(m);
        }
    };
    function getApp() {
        return document.getElementById('app') || document.body;
    }
    function applyTheme(theme) {
        var isLight = theme === 'light';
        document.documentElement.setAttribute('data-theme', isLight ? 'light' : 'dark');
        try { localStorage.setItem('bunker_theme', theme); } catch (_) {}
        var meta = document.getElementById('meta-theme-color');
        if (meta) meta.setAttribute('content', isLight ? '#f0f4f8' : '#0f1419');
    }
    function showLanding() {
        var app = getApp();
        app.innerHTML = '<div class="landing">' +
            '<h1>Бункер</h1>' +
            '<div class="tabs-auth">' +
            '<button type="button" data-tab="login" class="' + (authTab === 'login' ? 'active' : '') + '">Вход</button>' +
            '<button type="button" data-tab="register" class="' + (authTab === 'register' ? 'active' : '') + '">Регистрация</button>' +
            '</div>' +
            '<div class="card" id="auth-form"></div>' +
            '</div>';
        app.querySelector('.tabs-auth').addEventListener('click', function (ev) {
            var t = ev.target.dataset.tab;
            if (t) { authTab = t; showLanding(); }
        });
        var formEl = app.querySelector('#auth-form');
        if (authTab === 'login') {
            formEl.innerHTML = '<form id="f"><div class="form-group"><label>Никнейм</label><input type="text" name="nickname" required></div>' +
                '<div class="form-group"><label>Пароль</label><input type="password" name="password" required></div>' +
                '<button type="submit" class="btn btn-primary btn-block">Войти</button></form>';
        } else {
            formEl.innerHTML = '<form id="f"><div class="form-group"><label>Никнейм (2–20 символов)</label><input type="text" name="nickname" required></div>' +
                '<div class="form-group"><label>Пароль (мин. 4)</label><input type="password" name="password" required></div>' +
                '<button type="submit" class="btn btn-primary btn-block">Зарегистрироваться</button></form>';
        }
        formEl.querySelector('form').onsubmit = function (ev) {
            ev.preventDefault();
            var fd = new FormData(this);
            var nick = fd.get('nickname').trim();
            var pass = fd.get('password');
            if (authTab === 'login') {
                Api.login(nick, pass).then(function (r) {
                    if (r.success) {
                        user = r.data;
                        applyTheme(user.theme);
                        goMain();
                    } else {
                        UI.toast(r.error || 'Ошибка', 'error');
                    }
                });
            } else {
                Api.register(nick, pass).then(function (r) {
                    if (r.success) {
                        user = r.data;
                        applyTheme(user.theme);
                        goMain();
                    } else {
                        UI.toast(r.error || 'Ошибка', 'error');
                    }
                });
            }
        };
    }
    function goMain() {
        mainTab = 'rooms';
        gameStateChangeBound = false;
        showMain();
    }
    function showMain(tab) {
        if (tab) mainTab = tab;
        if (roomListRefreshInterval) { clearInterval(roomListRefreshInterval); roomListRefreshInterval = null; }
        var app = getApp();
        app.innerHTML = '<div class="main-screen">' +
            '<div id="main-content"></div>' +
            '<nav class="nav-bottom">' +
            '<a href="#" data-tab="profile"><span class="nav-icon">👤</span>Профиль</a>' +
            '<a href="#" data-tab="rooms"><span class="nav-icon">📋</span>Открытые</a>' +
            '<a href="#" data-tab="my"><span class="nav-icon">🔒</span>Приватные</a>' +
            '<a href="#" data-tab="create"><span class="nav-icon">➕</span>Создать</a>' +
            '</nav></div>';
        app.querySelector('.nav-bottom').addEventListener('click', function (ev) {
            ev.preventDefault();
            var link = ev.target && ev.target.closest && ev.target.closest('a');
            var t = link ? link.dataset.tab : null;
            if (t) { mainTab = t; showMain(); }
        });
        var content = app.querySelector('#main-content');
        var nav = app.querySelector('.nav-bottom');
        nav.querySelectorAll('a').forEach(function (a) {
            a.classList.toggle('active', a.dataset.tab === mainTab);
        });
        if (mainTab === 'profile') {
            Api.getProfile().then(function (r) {
                if (!r.success) { content.innerHTML = '<p>Ошибка загрузки</p>'; return; }
                var p = r.data;
                Api.getStats().then(function (sr) {
                    var stats = sr.success ? sr.data : { games_played: 0, wins: 0, win_rate: 0, history: [] };
                    content.innerHTML = '<div class="profile-section">' +
                        '<div class="profile-header">' +
                        '<label class="avatar-upload-wrap" title="Сменить фото">' +
                        '<span class="avatar-wrap" id="profile-avatar">' + avatarHtml(p.avatar_url, p.nickname) + '</span>' +
                        '<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" id="avatar-input" class="avatar-input">' +
                        '</label>' +
                        '<div class="profile-header-info"><strong>' + e(p.nickname) + '</strong><br><span class="text-muted">Игр: ' + stats.games_played + ', побед: ' + stats.wins + '</span></div>' +
                        '</div>' +
                        '<div class="profile-stats">' +
                        '<div class="stat"><div class="val">' + stats.games_played + '</div><div class="label">Игр</div></div>' +
                        '<div class="stat"><div class="val">' + stats.wins + '</div><div class="label">Побед</div></div>' +
                        '<div class="stat"><div class="val">' + stats.win_rate + '%</div><div class="label">% побед</div></div>' +
                        '</div>' +
                        '<div class="theme-row"><span class="theme-label">Тема</span><div class="theme-pills">' +
                        '<button type="button" class="theme-pill' + (p.theme === 'dark' ? ' active' : '') + '" data-theme="dark">Тёмная</button>' +
                        '<button type="button" class="theme-pill' + (p.theme === 'light' ? ' active' : '') + '" data-theme="light">Светлая</button></div></div>' +
                        '<button type="button" class="btn btn-secondary btn-block" id="btn-nick">Сменить никнейм</button>' +
                        '<button type="button" class="btn btn-secondary btn-block" id="btn-password">Сменить пароль</button>' +
                        '<button type="button" class="btn btn-secondary btn-block" id="logout-btn">Выйти</button>' +
                        '</div>';
                    var avatarInput = content.querySelector('#avatar-input');
                    var avatarWrap = content.querySelector('#profile-avatar');
                    if (avatarInput) avatarInput.onchange = function () {
                        var file = this.files && this.files[0];
                        if (!file) return;
                        Api.uploadAvatar(file).then(function (ar) {
                            if (ar.success && ar.data && ar.data.avatar_url) {
                                p.avatar_url = ar.data.avatar_url;
                                if (user) user.avatar_url = ar.data.avatar_url;
                                avatarWrap.innerHTML = avatarHtml(ar.data.avatar_url, p.nickname);
                                UI.toast('Фото обновлено');
                            } else UI.toast(ar.error || 'Ошибка загрузки', 'error');
                        });
                        this.value = '';
                    };
                    content.querySelectorAll('.theme-pill').forEach(function (btn) {
                        btn.onclick = function () {
                            var next = btn.dataset.theme;
                            Api.updateProfile({ theme: next }).then(function (ur) {
                                if (ur.success) { p.theme = next; user.theme = next; applyTheme(next); showMain('profile'); }
                            });
                        };
                    });
                    content.querySelector('#btn-nick').onclick = function () {
                        var modalContent = '<div class="form-group"><label>Новый никнейм (2–20 символов)</label><input type="text" id="modal-nick" value="' + e(p.nickname) + '" maxlength="20"></div><button type="button" class="btn btn-primary btn-block" id="modal-nick-save">Сохранить</button>';
                        UI.modal('Сменить никнейм', modalContent, function () {});
                        var inp = document.getElementById('modal-nick');
                        var saveBtn = document.getElementById('modal-nick-save');
                        if (saveBtn) saveBtn.onclick = function () {
                            var val = (inp && inp.value) ? inp.value.trim() : '';
                            if (val.length < 2) { UI.toast('Минимум 2 символа', 'error'); return; }
                            Api.updateProfile({ nickname: val }).then(function (ur) {
                                if (ur.success) { p.nickname = val; user.nickname = val; UI.closeModal(); showMain('profile'); UI.toast('Никнейм изменён'); }
                                else UI.toast(ur.error || 'Ошибка', 'error');
                            });
                        };
                    };
                    content.querySelector('#btn-password').onclick = function () {
                        var modalContent = '<div class="form-group"><label>Текущий пароль</label><input type="password" id="modal-old-pass"></div><div class="form-group"><label>Новый пароль (мин. 4)</label><input type="password" id="modal-new-pass"></div><button type="button" class="btn btn-primary btn-block" id="modal-pwd-save">Сохранить</button>';
                        UI.modal('Сменить пароль', modalContent, function () {});
                        var saveBtn = document.getElementById('modal-pwd-save');
                        if (saveBtn) saveBtn.onclick = function () {
                            var oldP = (document.getElementById('modal-old-pass') || {}).value || '';
                            var newP = (document.getElementById('modal-new-pass') || {}).value || '';
                            if (newP.length < 4) { UI.toast('Новый пароль минимум 4 символа', 'error'); return; }
                            Api.updateProfile({ old_password: oldP, new_password: newP }).then(function (ur) {
                                if (ur.success) { UI.closeModal(); UI.toast('Пароль изменён'); }
                                else UI.toast(ur.error || 'Ошибка', 'error');
                            });
                        };
                    };
                    content.querySelector('#logout-btn').onclick = function () {
                        Api.logout().then(function () {
                            user = null;
                            try { localStorage.removeItem(ROOM_STORAGE_KEY); } catch (_) {}
                            RT.disconnect();
                            showLanding();
                        });
                    };
                });
            });
        } else if (mainTab === 'rooms') {
            function renderOpenList() {
                Api.getRooms('open').then(function (r) {
                    if (!r.success) return;
                    var list = r.data || [];
                    content.innerHTML = '<h2>Открытые комнаты</h2><ul class="room-list">' +
                        list.map(function (room) {
                            return '<li><a href="#" data-room-id="' + room.id + '" data-code="' + e(room.code) + '">' +
                                e(room.name) + '<div class="room-meta">' + e(room.code) + ' · ' + room.player_count + '/' + room.max_players + ' · ' + e(room.creator_name) + '</div></a></li>';
                        }).join('') +
                        '</ul>';
                    if (list.length === 0) content.innerHTML += '<p class="text-muted">Нет открытых комнат</p>';
                    content.querySelectorAll('[data-room-id]').forEach(function (a) {
                        a.onclick = function (ev) {
                            ev.preventDefault();
                            Api.joinRoom(a.dataset.code, '').then(function (jr) {
                                if (jr.success) {
                                    try { localStorage.setItem(ROOM_STORAGE_KEY, String(jr.data.id)); } catch (_) {}
                                    showRoom(jr.data.id);
                                } else UI.toast(jr.error || 'Ошибка', 'error');
                            });
                        };
                    });
                });
            }
            renderOpenList();
            roomListRefreshInterval = setInterval(renderOpenList, 1000);
        } else if (mainTab === 'my') {
            function fillMyRoomsList(list) {
                var listEl = content.querySelector('#my-rooms-list');
                if (!listEl) return;
                listEl.innerHTML = list.map(function (room) {
                    return '<li><a href="#" data-room-id="' + room.id + '">' + e(room.name) + ' <span class="room-meta">' + e(room.code) + ' · ' + room.player_count + (room.status === 'waiting' ? ' игроков' : '') + '</span></a></li>';
                }).join('');
                if (list.length === 0) listEl.innerHTML = '<li class="text-muted">Вы пока ни в одной комнате.</li>';
                listEl.querySelectorAll('[data-room-id]').forEach(function (a) {
                    a.onclick = function (ev) {
                        ev.preventDefault();
                        try { localStorage.setItem(ROOM_STORAGE_KEY, String(a.dataset.roomId)); } catch (_) {}
                        showRoom(parseInt(a.dataset.roomId, 10));
                    };
                });
            }
            function renderMyList() {
                Api.getMyRooms().then(function (r) {
                    if (!r.success) return;
                    var list = r.data || [];
                    if (!content.querySelector('#join-f')) {
                        var joinHtml = '<div class="card" style="margin-bottom:20px"><h4>Войти в комнату</h4><form id="join-f"><div class="form-group"><label>Код комнаты</label><input type="text" name="code" placeholder="ABC123"></div><div class="form-group"><label>Пароль</label><input type="password" name="password" placeholder="Пароль комнаты"></div><button type="submit" class="btn btn-primary btn-block">Войти</button></form></div>';
                        content.innerHTML = '<h2>Приватные комнаты</h2>' +
                            '<p class="text-muted" style="margin-bottom:16px">Комнаты с паролем. Введите код и пароль, чтобы войти.</p>' +
                            joinHtml +
                            '<h3 style="font-size:16px;margin-bottom:10px">Мои комнаты</h3><p class="text-muted" style="margin-bottom:8px">Комнаты, в которых вы уже участвуете:</p><ul class="room-list" id="my-rooms-list"></ul>';
                        content.querySelector('#join-f').onsubmit = function (ev) {
                            ev.preventDefault();
                            var code = this.querySelector('[name=code]').value.trim().toUpperCase();
                            var pass = this.querySelector('[name=password]').value;
                            if (!code) { UI.toast('Введите код', 'error'); return; }
                            Api.joinRoom(code, pass).then(function (jr) {
                                if (jr.success) {
                                    try { localStorage.setItem(ROOM_STORAGE_KEY, String(jr.data.id)); } catch (_) {}
                                    showRoom(jr.data.id);
                                } else UI.toast(jr.error || 'Ошибка', 'error');
                            });
                        };
                    }
                    fillMyRoomsList(list);
                });
            }
            renderMyList();
            roomListRefreshInterval = setInterval(function () {
                Api.getMyRooms().then(function (r) {
                    if (r.success && content.querySelector('#my-rooms-list')) fillMyRoomsList(r.data || []);
                });
            }, 1000);
        } else {
            content.innerHTML = '<h2>Создать комнату</h2><div class="card">' +
                '<form id="create-f">' +
                '<div class="form-group"><label>Название</label><input type="text" name="name" value="Бункер"></div>' +
                '<div class="form-group"><label>Макс. игроков</label><input type="number" name="max_players" value="8" min="4" max="16"></div>' +
                '<div class="form-group"><label>Мест в бункере</label><input type="number" name="bunker_slots" value="4" min="2"></div>' +
                '<div class="form-group"><label>Время обсуждения (сек)</label><input type="number" name="discuss_time" value="300" min="30" max="600"></div>' +
                '<div class="form-group"><label>Время голосования (сек)</label><input type="number" name="vote_time" value="60" min="15" max="300"></div>' +
                '<div class="form-group"><span class="field-label">Доступ</span><div class="toggle-row"><span class="toggle-label">Публичная (без пароля)</span><label class="toggle-switch"><input type="checkbox" name="is_public" checked><span class="toggle-slider"></span></label></div></div>' +
                '<div class="form-group"><label>Пароль (если приватная)</label><input type="password" name="password"></div>' +
                '<button type="submit" class="btn btn-primary btn-block">Создать</button></form></div>';
            content.querySelector('#create-f').onsubmit = function (ev) {
                ev.preventDefault();
                var form = this;
                var isPublic = form.querySelector('[name=is_public]').checked;
                Api.createRoom({
                    name: form.querySelector('[name=name]').value || 'Бункер',
                    max_players: parseInt(form.querySelector('[name=max_players]').value, 10) || 8,
                    bunker_slots: Math.max(2, parseInt(form.querySelector('[name=bunker_slots]').value, 10) || 4),
                    discuss_time: parseInt(form.querySelector('[name=discuss_time]').value, 10) || 300,
                    vote_time: parseInt(form.querySelector('[name=vote_time]').value, 10) || 60,
                    cards_per_round: [2, 1, 1, 1],
                    superpowers_on: true,
                    is_public: isPublic,
                    password: isPublic ? '' : form.querySelector('[name=password]').value
                }).then(function (r) {
                    if (r.success) {
                        try { localStorage.setItem(ROOM_STORAGE_KEY, String(r.data.id)); } catch (_) {}
                        showRoom(r.data.id);
                    } else UI.toast(r.error || 'Ошибка', 'error');
                });
            };
        }
    }
    function showRoom(roomId) {
        Api.getRoomDetail(roomId).then(function (r) {
            if (!r.success) {
                if (r.error && r.error.indexOf('не в этой') !== -1) {
                    try { localStorage.removeItem(ROOM_STORAGE_KEY); } catch (_) {}
                    RT.disconnect();
                    goMain();
                    UI.toast('Вас выгнали из комнаты', 'error');
                } else {
                    UI.toast(r.error || 'Ошибка', 'error');
                }
                return;
            }
            currentRoom = r.data;
            if (currentRoom.active_game) {
                RT.connect(roomId);
                RT.setLastPhase(currentRoom.active_game.phase, currentRoom.active_game.round_num);
                showGame(roomId);
                return;
            }
            RT.connect(roomId);
            function renderRoomBody(roomData) {
                currentRoom = roomData;
                var app = getApp();
                var header = app.querySelector('.room-header');
                var listEl = app.querySelector('#players-list');
                var actionsEl = app.querySelector('.room-actions');
                if (!listEl || !actionsEl) return;
                if (header) {
                    header.querySelector('.code').textContent = roomData.code || '';
                    var sub = header.querySelector('.room-header-sub');
                    if (sub) sub.textContent = (roomData.name || '') + ' · мест в бункере: ' + (roomData.bunker_slots || 0);
                }
                listEl.innerHTML = '';
                (roomData.players || []).forEach(function (p) {
                    var li = document.createElement('li');
                    li.className = 'player-row';
                    li.innerHTML = '<div class="avatar-wrap">' + avatarHtml(p.avatar_url, p.nickname) + '</div>' +
                        '<span class="name">' + e(p.nickname) + (p.is_creator ? ' <span class="badge">Создатель</span>' : '') + '</span>' +
                        (roomData.is_creator && !p.is_creator && !p.is_me ? '<button type="button" class="kick-btn" data-uid="' + p.id + '">✕</button>' : '');
                    listEl.appendChild(li);
                    var kickBtn = li.querySelector('.kick-btn');
                    if (kickBtn) kickBtn.onclick = function () {
                        Api.kickPlayer(roomId, parseInt(kickBtn.dataset.uid, 10)).then(function (kr) {
                            if (kr.success) Api.getRoomDetail(roomId).then(function (rr) { if (rr.success) renderRoomBody(rr.data); });
                            else UI.toast(kr.error || 'Ошибка', 'error');
                        });
                    };
                });
            }
            function bindRoomButtons() {
                var app = getApp();
                var startBtn = app.querySelector('#start-btn');
                var leaveBtn = app.querySelector('#leave-btn');
                if (startBtn) startBtn.onclick = function () {
                    if (currentRoom.player_count < 2) { UI.toast('Нужно минимум 2 игрока', 'error'); return; }
                    Api.startGame(roomId).then(function (sr) {
                        if (sr.success) showGame(roomId);
                        else UI.toast(sr.error || 'Ошибка', 'error');
                    });
                };
                if (leaveBtn) leaveBtn.onclick = function () {
                    Api.leaveRoom(roomId).then(function (lr) {
                        try { localStorage.removeItem(ROOM_STORAGE_KEY); } catch (_) {}
                        RT.disconnect();
                        currentRoom = null;
                        goMain();
                    });
                };
            }
            var app = getApp();
            app.innerHTML = '<div class="room-header">' +
                '<div class="code-label">Код комнаты</div><div class="code">' + e(currentRoom.code) + '</div>' +
                '<div class="room-header-sub" style="margin-top:8px;font-size:13px;color:var(--text-muted)">' + e(currentRoom.name) + ' · мест в бункере: ' + currentRoom.bunker_slots + '</div>' +
                '</div>' +
                '<ul class="players-list" id="players-list"></ul>' +
                '<div class="room-actions">' +
                (currentRoom.is_creator ? '<button type="button" class="btn btn-primary btn-block" id="start-btn">Начать игру</button>' : '') +
                '<button type="button" class="btn btn-secondary btn-block" id="leave-btn">Покинуть комнату</button>' +
                '</div>';
            renderRoomBody(currentRoom);
            bindRoomButtons();
            var roomPoll = setInterval(function () {
                Api.getRoomDetail(roomId).then(function (rr) {
                    if (!rr.success) return;
                    var data = rr.data;
                    var meIn = (data.players || []).some(function (p) { return p.is_me; });
                    if (!meIn) {
                        clearInterval(roomPoll);
                        try { localStorage.removeItem(ROOM_STORAGE_KEY); } catch (_) {}
                        RT.disconnect();
                        goMain();
                        UI.toast('Вас выгнали из комнаты', 'error');
                        return;
                    }
                    if (data.active_game) {
                        clearInterval(roomPoll);
                        currentRoom = data;
                        showGame(roomId);
                    } else {
                        renderRoomBody(data);
                    }
                });
            }, 1000);
        });
    }
    function showGame(roomId) {
        if (!gameStateChangeBound) {
            gameStateChangeBound = true;
            RT.on('state_change', function () {
                Api.getGameState(roomId).then(function (rr) {
                    if (rr.success) { currentGame = rr.data; lastPhase = currentGame.game.phase; lastRound = currentGame.game.round_num; renderGame(roomId); }
                });
            });
        }
        Api.getGameState(roomId).then(function (r) {
            if (!r.success) {
                UI.toast(r.error || 'Ошибка', 'error');
                return;
            }
            currentGame = r.data;
            lastPhase = currentGame.game.phase;
            lastRound = currentGame.game.round_num;
            RT.setLastPhase(lastPhase, lastRound);
            if (r.data.last_eliminated && (r.data.game.phase === 'revealing' || r.data.game.phase === 'finished')) {
                var roundKey = r.data.game.phase + '-' + (r.data.game.phase === 'revealing' ? r.data.game.round_num - 1 : r.data.game.round_num);
                if (lastEliminatedToastRound !== roundKey) {
                    lastEliminatedToastRound = roundKey;
                    UI.toast(r.data.last_eliminated + ' исключён из бункера');
                }
            }
            renderGame(roomId);
        });
    }
    function renderGame(roomId) {
        var g = currentGame.game;
        var phase = g.phase;
        var app = getApp();
        var timerHtml = '';
        if (g.time_left != null && phase !== 'cards' && phase !== 'finished') {
            timerHtml = '<span class="game-timer" id="game-timer">' + formatTime(g.time_left) + '</span>';
        }
        app.innerHTML = '<div class="game-header">' +
            '<div><strong>' + e(currentGame.room.name) + '</strong><div class="game-phase">' + phaseName(phase) + (phase !== 'cards' && phase !== 'finished' ? ' · Раунд ' + g.round_num : '') + '</div></div>' +
            '<div class="game-header-right">' + timerHtml + '<button type="button" class="btn btn-secondary btn-sm" id="game-leave-btn">Выйти</button></div>' +
            '</div>';
        if (currentGame.catastrophe && currentGame.catastrophe.type) {
            var ban = document.createElement('div');
            ban.className = 'catastrophe-banner';
            ban.innerHTML = '<h3>' + e(currentGame.catastrophe.type) + '</h3><p>' + e(currentGame.catastrophe.description || '') + '</p>';
            app.appendChild(ban);
        }
        if (phase === 'cards') {
            renderCardsPhase(roomId, app);
        } else if (phase === 'revealing') {
            renderRevealingPhase(roomId, app);
        } else if (phase === 'discussing') {
            renderDiscussingPhase(roomId, app);
        } else if (phase === 'voting') {
            renderVotingPhase(roomId, app);
        } else if (phase === 'finished') {
            renderFinishedPhase(roomId, app);
        }
        if (votingPollInterval) { clearInterval(votingPollInterval); votingPollInterval = null; }
        if (cardsPollInterval) { clearInterval(cardsPollInterval); cardsPollInterval = null; }
        if (revealingPollInterval) { clearInterval(revealingPollInterval); revealingPollInterval = null; }
        if (phase === 'cards') {
            cardsPollInterval = setInterval(function () {
                Api.getGameState(roomId).then(function (r) {
                    if (!r.success) return;
                    currentGame = r.data;
                    if (currentGame.game.phase !== 'cards') return;
                    lastPhase = currentGame.game.phase;
                    lastRound = currentGame.game.round_num;
                    renderGame(roomId);
                });
            }, 1000);
        } else if (phase === 'revealing') {
            revealingPollInterval = setInterval(function () {
                Api.getGameState(roomId).then(function (r) {
                    if (!r.success) return;
                    currentGame = r.data;
                    if (currentGame.game.phase !== 'revealing') { renderGame(roomId); return; }
                    lastPhase = currentGame.game.phase;
                    lastRound = currentGame.game.round_num;
                    renderGame(roomId);
                });
            }, 1000);
        } else if (phase === 'voting') {
            votingPollInterval = setInterval(function () {
                Api.getGameState(roomId).then(function (r) {
                    if (!r.success) return;
                    currentGame = r.data;
                    if (currentGame.game.phase !== 'voting') return;
                    lastPhase = currentGame.game.phase;
                    lastRound = currentGame.game.round_num;
                    renderGame(roomId);
                });
            }, 1000);
        }
        var gameLeaveBtn = app.querySelector('#game-leave-btn');
        if (gameLeaveBtn) gameLeaveBtn.onclick = function () {
            Api.leaveRoom(roomId).then(function (lr) {
                try { localStorage.removeItem(ROOM_STORAGE_KEY); } catch (_) {}
                RT.disconnect();
                currentRoom = null;
                currentGame = null;
                gameStateChangeBound = false;
                goMain();
            });
        };
        if (g.time_left != null && phase !== 'cards' && phase !== 'finished') {
            startTimer(app.querySelector('#game-timer'), g.time_left, function () {
                Api.getGameState(roomId).then(function (rr) {
                    if (rr.success) { currentGame = rr.data; renderGame(roomId); }
                });
            });
        }
    }
    function startTimer(el, sec, onZero) {
        if (!el) return;
        var s = sec;
        var iv = setInterval(function () {
            s--;
            el.textContent = formatTime(s);
            if (s <= 0) { clearInterval(iv); if (onZero) onZero(); }
        }, 1000);
    }
    function renderExcludedSection() {
        var eliminated = (currentGame.players || []).filter(function (p) { return p.is_eliminated; });
        if (eliminated.length === 0) return '';
        var html = '<div class="excluded-section' + (excludedSectionHidden ? ' excluded-section-collapsed' : '') + '"><button type="button" class="excluded-toggle" id="excluded-toggle">' + (excludedSectionHidden ? 'Показать исключённых (' + eliminated.length + ')' : 'Скрыть исключённых') + '</button><div class="excluded-grid">';
        eliminated.forEach(function (p) {
            var card = p.full_card;
            var cardHtml = card ? (
                '<div class="attr-row">' + attrLabel('profession') + ': ' + e(card.profession) + '</div>' +
                '<div class="attr-row">' + attrLabel('health') + ': ' + e(card.health) + '</div>' +
                '<div class="attr-row">' + attrLabel('phobia') + ': ' + e(card.phobia) + '</div>' +
                '<div class="attr-row">' + attrLabel('bio') + ': ' + e(shortVal(card.bio)) + '</div>' +
                '<div class="attr-row attr-row-extra">' + attrLabel('extra') + ': <span class="extra-formatted">' + formatExtraHtml(card.extra) + '</span></div>' +
                '<div class="attr-row">' + attrLabel('baggage') + ': ' + e(card.baggage) + '</div>' +
                (card.special_condition ? '<div class="attr-row">' + attrLabel('special_condition') + ': ' + e(card.special_condition) + '</div>' : '')
            ) : '';
            html += '<div class="player-card excluded-card"><div class="avatar-wrap">' + avatarHtml(p.avatar_url, p.nickname) + '</div><div class="nick">' + e(p.nickname) + '</div><div class="excluded-full-card">' + cardHtml + '</div></div>';
        });
        return html + '</div></div>';
    }
    function renderCardsPhase(roomId, app) {
        var meEliminated = (currentGame.players || []).some(function (p) { return p.user_id === user.id && p.is_eliminated; });
        var myCard = currentGame.my_card;
        var cardHtml = '';
        if (!meEliminated && myCard) {
            cardHtml = '<div class="my-card-box"><h4>Моя карточка — все ваши характеристики</h4>' +
                '<div class="attr-row">' + attrLabel('profession') + ': ' + e(myCard.profession) + '</div>' +
                '<div class="attr-row">' + attrLabel('health') + ': ' + e(myCard.health) + '</div>' +
                '<div class="attr-row">' + attrLabel('phobia') + ': ' + e(myCard.phobia) + '</div>' +
                '<div class="attr-row">' + attrLabel('bio') + ': ' + e(shortVal(myCard.bio)) + '</div>' +
                '<div class="attr-row attr-row-extra">' + attrLabel('extra') + ':<br><span class="extra-formatted">' + formatExtraHtml(myCard.extra) + '</span></div>' +
                '<div class="attr-row">' + attrLabel('baggage') + ': ' + e(myCard.baggage) + '</div>' +
                (myCard.special_condition ? '<div class="special-condition-card"><div class="attr-row">' + attrLabel('special_condition') + ': ' + e(myCard.special_condition) + '</div><button type="button" class="btn btn-secondary btn-sm" id="apply-special-btn">Применить</button></div>' : '') +
                '</div>';
        }
        if (meEliminated) cardHtml = '<div class="spectator-notice">Вы исключены. Режим наблюдателя — вы можете только смотреть.</div>' + cardHtml;
        var grid = '<div class="players-grid">';
        (currentGame.players || []).forEach(function (p) {
            if (p.is_eliminated) return;
            grid += '<div class="player-card"><div class="avatar-wrap">' + avatarHtml(p.avatar_url, p.nickname) + '</div><div class="nick">' + e(p.nickname) + '</div>' +
                (p.is_ready ? '<div style="color:var(--success)">✓ Готов</div>' : '') +
                (p.reveals && p.reveals.profession ? '<div class="reveals"><div>' + e(p.reveals.profession) + '</div></div>' : '') +
                '</div>';
        });
        grid += '</div>';
        var meReady = (currentGame.players || []).some(function (p) { return p.user_id === user.id && p.is_ready; });
        var readyBlock = meEliminated ? '' : (meReady
            ? '<div class="waiting-notice"><span class="waiting-notice-icon">✓</span> Вы готовы. Ожидаем остальных.</div>'
            : '<div style="padding:16px"><button type="button" class="btn btn-primary btn-block" id="ready-btn">Я готов</button></div>');
        var wrap = document.createElement('div');
        wrap.innerHTML = cardHtml + grid + readyBlock + renderExcludedSection();
        app.appendChild(wrap);
        var toggleBtn = app.querySelector('#excluded-toggle');
        if (toggleBtn) toggleBtn.onclick = function () { excludedSectionHidden = !excludedSectionHidden; try { localStorage.setItem(EXCLUDED_HIDDEN_KEY, excludedSectionHidden ? 'true' : 'false'); } catch (_) {} renderGame(roomId); };
        var readyBtn = wrap.querySelector('#ready-btn');
        if (readyBtn) readyBtn.onclick = function () {
            Api.gameReady(currentGame.game.id).then(function (rr) {
                if (rr.success) showGame(roomId);
            });
        };
        var applySpecialBtn = wrap.querySelector('#apply-special-btn');
        if (applySpecialBtn) applySpecialBtn.onclick = function () {
            Api.revealAttr(currentGame.game.id, 'special_condition').then(function (rr) {
                if (rr.success) showGame(roomId);
                else UI.toast(rr.error || 'Ошибка', 'error');
            });
        };
    }
    function renderRevealingPhase(roomId, app) {
        var meEliminated = (currentGame.players || []).some(function (p) { return p.user_id === user.id && p.is_eliminated; });
        var myCard = currentGame.my_card;
        var limit = currentGame.reveal_limit || 1;
        var myCount = currentGame.my_reveal_count || 0;
        var topHtml = '';
        if (meEliminated) {
            topHtml = '<div class="spectator-notice">Вы исключены. Режим наблюдателя.</div>';
        } else if (myCount >= limit) {
            topHtml = '<div class="waiting-notice"><span class="waiting-notice-icon">✓</span> Вы раскрыли карты. Ожидаем остальных.</div>';
        } else {
            var attrs = [
                { key: 'profession', val: myCard && myCard.profession },
                { key: 'health', val: myCard && myCard.health },
                { key: 'phobia', val: myCard && myCard.phobia },
                { key: 'bio', val: myCard && myCard.bio },
                { key: 'extra', val: myCard && myCard.extra },
                { key: 'baggage', val: myCard && myCard.baggage },
                { key: 'special_condition', val: myCard && myCard.special_condition }
            ];
            var rows = '';
            attrs.forEach(function (a) {
                var revMap = (currentGame.players && currentGame.players.find(function (p) { return p.user_id === user.id; }));
                revMap = (revMap && revMap.reveals) || {};
                var isRevealedToOthers = revMap[a.key] !== undefined;
                var realVal = a.val != null ? (typeof a.val === 'object' ? shortVal(a.val) : String(a.val)) : '—';
                var showVal = realVal;
                var clickable = !isRevealedToOthers && myCount < limit && a.key !== 'profession';
                var cl = 'attr-row' + (selectedAttr === a.key ? ' attribute-row-selected' : '') + (clickable ? ' clickable' : '');
                var hint = clickable ? ' (нажмите, чтобы раскрыть остальным)' : (isRevealedToOthers ? ' ✓ раскрыто' : '');
                rows += '<div class="' + cl + '" data-attr="' + a.key + '">' + attrLabel(a.key) + ': ' + e(showVal) + '<span class="attr-hint">' + e(hint) + '</span></div>';
            });
            topHtml = '<div class="my-card-box"><h4>Ваша карточка — выберите, что раскрыть остальным (' + myCount + '/' + limit + ')</h4><div id="attr-list">' + rows + '</div>' +
                (myCard && myCard.special_condition ? '<div class="special-condition-card" style="margin-top:12px"><strong>' + attrLabel('special_condition') + ':</strong> ' + e(myCard.special_condition) + ' <button type="button" class="btn btn-secondary btn-sm" id="apply-special-reveal-btn">Применить</button></div>' : '') +
                '<button type="button" class="btn btn-primary" id="reveal-btn" style="margin-top:12px">Раскрыть выбранное</button></div>';
        }
        var wrap = document.createElement('div');
        wrap.innerHTML = topHtml +
            '<div class="players-grid">' +
            (currentGame.players || []).filter(function (p) { return !p.is_eliminated; }).map(function (p) {
                var r = (p.reveals || {});
                var revParts = Object.keys(r).map(function (k) {
                    var v = k === 'extra' ? formatExtraHtml(r[k]) : e(formatRevealVal(r[k], k));
                    return attrLabel(k) + ': ' + v;
                });
                var revList = revParts.join('<br>');
                if (p.user_id === user.id && p.ready_this_round) revList = '<span class="ready-badge revealed-by-me">✓</span>' + (revList ? '<br>' + revList : '');
                else if (p.ready_this_round && Object.keys(r).length === 0) revList = '<span class="ready-badge">✓</span>';
                else if (p.ready_this_round) revList = revList + '<br><span class="ready-badge">✓ Выбрал</span>';
                return '<div class="player-card"><div class="avatar-wrap">' + avatarHtml(p.avatar_url, p.nickname) + '</div><div class="nick">' + e(p.nickname) + '</div><div class="reveals">' + (revList || '—') + '</div></div>';
            }).join('') +
            '</div>' + renderExcludedSection();
        app.appendChild(wrap);
        var toggleBtn = app.querySelector('#excluded-toggle');
        if (toggleBtn) toggleBtn.onclick = function () { excludedSectionHidden = !excludedSectionHidden; try { localStorage.setItem(EXCLUDED_HIDDEN_KEY, excludedSectionHidden ? 'true' : 'false'); } catch (_) {} renderGame(roomId); };
        if (!meEliminated) {
            wrap.querySelectorAll('#attr-list .attr-row.clickable').forEach(function (row) {
                row.onclick = function () {
                    selectedAttr = row.dataset.attr;
                    renderGame(roomId);
                };
            });
            var revealBtn = wrap.querySelector('#reveal-btn');
            if (revealBtn) revealBtn.onclick = function () {
                if (!selectedAttr) { UI.toast('Выберите характеристику', 'error'); return; }
                Api.revealAttr(currentGame.game.id, selectedAttr).then(function (rr) {
                    if (rr.success) { selectedAttr = null; showGame(roomId); }
                    else UI.toast(rr.error || 'Ошибка', 'error');
                });
            };
            var applyRevealBtn = wrap.querySelector('#apply-special-reveal-btn');
            if (applyRevealBtn) applyRevealBtn.onclick = function () {
                Api.revealAttr(currentGame.game.id, 'special_condition').then(function (rr) {
                    if (rr.success) showGame(roomId);
                    else UI.toast(rr.error || 'Ошибка', 'error');
                });
            };
        }
    }
    function renderDiscussingPhase(roomId, app) {
        var meEliminated = (currentGame.players || []).some(function (p) { return p.user_id === user.id && p.is_eliminated; });
        var chatInputRow = meEliminated
            ? '<div class="chat-disabled eliminated-notice">Вы исключены — чат недоступен.</div>'
            : '<div class="chat-input-row"><input type="text" placeholder="Сообщение..." id="chat-input" maxlength="500"><button type="button" class="btn btn-primary" id="chat-send">Отпр.</button></div>';
        var wrap = document.createElement('div');
        wrap.innerHTML = '<div class="players-grid">' +
            (currentGame.players || []).filter(function (p) { return !p.is_eliminated; }).map(function (p) {
                var r = (p.reveals || {});
                var revParts = Object.keys(r).map(function (k) {
                    var v = k === 'extra' ? formatExtraHtml(r[k]) : e(formatRevealVal(r[k], k)).replace(/\n/g, '<br>');
                    return attrLabel(k) + ': ' + v;
                });
                var revList = revParts.join('<br>');
                return '<div class="player-card"><div class="avatar-wrap">' + avatarHtml(p.avatar_url, p.nickname) + '</div><div class="nick">' + e(p.nickname) + '</div><div class="reveals">' + (revList || '—') + '</div></div>';
            }).join('') +
            '</div>' + renderExcludedSection() + '<div class="chat-box"><div class="chat-messages" id="chat-msgs"></div>' + chatInputRow + '</div>';
        app.appendChild(wrap);
        var toggleBtn = app.querySelector('#excluded-toggle');
        if (toggleBtn) toggleBtn.onclick = function () { excludedSectionHidden = !excludedSectionHidden; try { localStorage.setItem(EXCLUDED_HIDDEN_KEY, excludedSectionHidden ? 'true' : 'false'); } catch (_) {} renderGame(roomId); };
        var msgsEl = wrap.querySelector('#chat-msgs');
        var inputEl = wrap.querySelector('#chat-input');
        function addMsg(m) {
            if (!m || !m.id) return;
            if (msgsEl.querySelector('[data-msg-id="' + m.id + '"]')) return;
            var div = document.createElement('div');
            div.className = 'chat-msg' + (m.is_me ? ' me' : '');
            div.setAttribute('data-msg-id', m.id);
            div.innerHTML = '<div class="avatar-wrap">' + avatarHtml(m.avatar_url, m.nickname) + '</div>' +
                '<div><div class="nick">' + e(m.nickname) + '</div><div class="bubble">' + e(m.text) + '</div></div>';
            msgsEl.appendChild(div);
            msgsEl.scrollTop = msgsEl.scrollHeight;
            if (m.id > lastMsgId) lastMsgId = m.id;
        }
        Api.getMessages(roomId, 0).then(function (mr) {
            if (mr.success && mr.data) mr.data.forEach(function (m) { addMsg(m); if (m.id > lastMsgId) lastMsgId = m.id; });
        });
        RT.setLastMsgId(lastMsgId);
        RT.on('new_messages', function (data) {
            if (Array.isArray(data) && data.length) data.forEach(addMsg);
            else Api.getMessages(roomId, lastMsgId).then(function (mr) {
                if (mr.success && mr.data) mr.data.forEach(addMsg);
            });
        });
        var sendBtn = wrap.querySelector('#chat-send');
        function doSend() {
            var text = inputEl.value.trim();
            if (!text || sendBtn.disabled) return;
            sendBtn.disabled = true;
            Api.sendMessage(roomId, text).then(function (sr) {
                sendBtn.disabled = false;
                if (sr.success && sr.data) {
                    addMsg(sr.data);
                    lastMsgId = Math.max(lastMsgId, sr.data.id);
                    RT.setLastMsgId(lastMsgId);
                    inputEl.value = '';
                } else if (!sr.success) UI.toast(sr.error || 'Ошибка', 'error');
            }).catch(function () { sendBtn.disabled = false; });
        }
        if (inputEl && !meEliminated) {
            var sendBtn = wrap.querySelector('#chat-send');
            if (sendBtn) sendBtn.onclick = function () { doSend(); };
            inputEl.onkeydown = function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); doSend(); } };
        }
        if (currentRoom && currentRoom.is_creator) {
            var adv = document.createElement('div');
            adv.style.padding = '16px';
            adv.innerHTML = '<button type="button" class="btn btn-secondary btn-block" id="advance-vote-btn">Перейти к голосованию</button>';
            app.appendChild(adv);
            adv.querySelector('#advance-vote-btn').onclick = function () {
                Api.advancePhase(currentGame.game.id).then(function (ar) {
                    if (ar.success) showGame(roomId);
                });
            };
        }
    }
    function renderVotingPhase(roomId, app) {
        var meEliminated = (currentGame.players || []).some(function (p) { return p.user_id === user.id && p.is_eliminated; });
        var voted = currentGame.votes && currentGame.votes.i_voted;
        var tieCandidates = (currentGame.game && currentGame.game.tie_candidates) || null;
        var voteTargets = (currentGame.players || []).filter(function (p) {
            if (p.is_eliminated || p.user_id === user.id) return false;
            if (tieCandidates && tieCandidates.length) return tieCandidates.indexOf(p.user_id) !== -1;
            return true;
        });
        var wrap = document.createElement('div');
        wrap.innerHTML = (meEliminated
            ? '<div class="chat-disabled eliminated-notice">Вы исключены из бункера. Ожидайте окончания игры.</div>'
            : voted
                ? '<div class="waiting-notice"><span class="waiting-notice-icon">✓</span> Вы проголосовали. Ожидаем остальных.</div>'
                : (tieCandidates && tieCandidates.length ? '<div class="chat-disabled tie-banner">Переголосование: выберите одного из игроков ниже.</div>' : '<div class="chat-disabled">Чат закрыт во время голосования.</div>')) +
            renderExcludedSection() +
            '<div class="players-grid">' +
            (meEliminated ? '' : voteTargets.map(function (p) {
                var cnt = (currentGame.votes && currentGame.votes.counts && currentGame.votes.counts[p.user_id]) || 0;
                return '<div class="player-card"><div class="avatar-wrap">' + avatarHtml(p.avatar_url, p.nickname) + '</div><div class="nick">' + e(p.nickname) + '</div>' +
                    (cnt ? '<div style="font-size:12px;color:var(--text-muted)">Голосов: ' + cnt + '</div>' : '') +
                    (!voted ? '<button type="button" class="btn btn-danger vote-btn" data-target="' + p.user_id + '">Голосовать</button>' : '') +
                    '</div>';
            }).join('')) +
            '</div>';
        app.appendChild(wrap);
        var toggleBtn = app.querySelector('#excluded-toggle');
        if (toggleBtn) toggleBtn.onclick = function () { excludedSectionHidden = !excludedSectionHidden; try { localStorage.setItem(EXCLUDED_HIDDEN_KEY, excludedSectionHidden ? 'true' : 'false'); } catch (_) {} renderGame(roomId); };
        if (!voted && !meEliminated) {
            wrap.querySelectorAll('.vote-btn').forEach(function (btn) {
                btn.onclick = function () {
                    Api.castVote(currentGame.game.id, parseInt(btn.dataset.target, 10)).then(function (vr) {
                        if (vr.success) showGame(roomId);
                        else UI.toast(vr.error || 'Ошибка', 'error');
                    });
                };
            });
        }
    }
    function renderFinishedPhase(roomId, app) {
        var survivors = (currentGame.players || []).filter(function (p) { return !p.is_eliminated; });
        var iSurvived = survivors.some(function (p) { return p.user_id === user.id; });
        var wrap = document.createElement('div');
        wrap.className = 'finished-screen ' + (iSurvived ? 'finished-victory' : 'finished-defeat');
        wrap.innerHTML = (iSurvived
            ? '<div class="result-banner result-victory"><span class="result-emoji">🏆</span><h2>Победа!</h2><p>Вы остались в бункере.</p></div>'
            : '<div class="result-banner result-defeat"><span class="result-emoji">💀</span><h2>Поражение</h2><p>Вас исключили из бункера.</p></div>') +
            '<p style="text-align:center;color:var(--text-muted);margin-top:16px">Выжившие:</p><ul class="survivors-list">' +
            survivors.map(function (p) {
                var r = (p.reveals || {});
                var revList = Object.keys(r).map(function (k) { var v = k === 'extra' ? formatExtraHtml(r[k]) : e(formatRevealVal(r[k], k)).replace(/\n/g, '<br>'); return attrLabel(k) + ': ' + v; }).join(' · ');
                return '<li><div class="nick">' + avatarHtml(p.avatar_url, p.nickname) + ' ' + e(p.nickname) + '</div><div class="survivor-reveals">' + revList + '</div></li>';
            }).join('') +
            '</ul><div class="finished-actions">' +
            (currentRoom && currentRoom.is_creator ? '<button type="button" class="btn btn-primary btn-block" id="restart-btn">Новая игра</button>' : '') +
            '<button type="button" class="btn btn-secondary btn-block" id="home-btn">На главную</button></div>';
        app.appendChild(wrap);
        if (wrap.querySelector('#restart-btn')) {
            wrap.querySelector('#restart-btn').onclick = function () {
                var rid = (currentRoom && currentRoom.id) || roomId;
                Api.restartGame(rid).then(function (rr) {
                    if (rr.success) {
                        currentRoom.status = 'waiting';
                        currentRoom.active_game = null;
                        UI.toast('Комната готова к новой игре');
                        showRoom(rid);
                    } else UI.toast(rr.error || 'Ошибка', 'error');
                });
            };
        }
        wrap.querySelector('#home-btn').onclick = function () {
            try { localStorage.removeItem(ROOM_STORAGE_KEY); } catch (_) {}
            RT.disconnect();
            currentRoom = null;
            currentGame = null;
            gameStateChangeBound = false;
            lastEliminatedToastRound = null;
            goMain();
        };
    }
    window.App = {
        init: function () {
            var theme = null;
            try { theme = localStorage.getItem('bunker_theme'); } catch (_) {}
            applyTheme(theme || 'dark');
            UI.showLoading();
            Api.me().then(function (r) {
                if (r.success) {
                    user = r.data;
                    applyTheme(user.theme || 'dark');
                    try { excludedSectionHidden = localStorage.getItem(EXCLUDED_HIDDEN_KEY) === 'true'; } catch (_) {}
                    var savedRoom = null;
                    try { savedRoom = localStorage.getItem(ROOM_STORAGE_KEY); } catch (_) {}
                    if (savedRoom) {
                        var rid = parseInt(savedRoom, 10);
                        Api.getRoomDetail(rid).then(function (rr) {
                            if (!rr.success) {
                                try { localStorage.removeItem(ROOM_STORAGE_KEY); } catch (_) {}
                                showMain();
                                return;
                            }
                            var data = rr.data;
                            var meIn = (data.players || []).some(function (p) { return p.is_me; });
                            if (!meIn) {
                                try { localStorage.removeItem(ROOM_STORAGE_KEY); } catch (_) {}
                                showMain();
                                UI.toast('Вас выгнали из комнаты', 'error');
                                return;
                            }
                            currentRoom = data;
                            if (data.active_game) {
                                RT.connect(rid);
                                showGame(rid);
                            } else {
                                showRoom(rid);
                            }
                        });
                    } else {
                        showMain();
                    }
                } else {
                    showLanding();
                }
            });
        }
    };
    var Api = window.Api;
    var RT = window.RT;
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { App.init(); });
    } else {
        App.init();
    }
})();