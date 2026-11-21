(function () {
    'use strict';
    var base = (window.BUNKER_CONFIG && window.BUNKER_CONFIG.baseUrl) || '';
    function request(method, url, body) {
        var opts = {
            method: method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' }
        };
        if (body !== undefined && method !== 'GET') {
            opts.body = typeof body === 'string' ? body : JSON.stringify(body);
        }
        return fetch(base + url, opts).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok && data && !data.error) {
                    data = { success: false, error: 'Ошибка ' + res.status };
                }
                return data;
            });
        }).catch(function (err) {
            if (typeof console !== 'undefined' && console.error) console.error('API error', url, err);
            return { success: false, error: 'Сеть недоступна' };
        });
    }
    window.Api = {
        register: function (nickname, password) {
            return request('POST', '/register.php', { nickname: nickname, password: password });
        },
        login: function (nickname, password) {
            return request('POST', '/login.php', { nickname: nickname, password: password });
        },
        logout: function () {
            return request('POST', '/logout.php');
        },
        me: function () {
            return request('GET', '/me.php');
        },
        getRooms: function (type) {
            return request('GET', '/rooms_list.php?type=' + (type || 'open'));
        },
        getMyRooms: function () {
            return request('GET', '/rooms_list.php?type=my');
        },
        getRoomDetail: function (roomId) {
            return request('GET', '/room_detail.php?room_id=' + roomId);
        },
        createRoom: function (params) {
            return request('POST', '/create_room.php', params);
        },
        joinRoom: function (code, password) {
            return request('POST', '/join_room.php', { code: code, password: password || '' });
        },
        leaveRoom: function (roomId) {
            return request('POST', '/leave_room.php', { room_id: roomId });
        },
        kickPlayer: function (roomId, userId) {
            return request('POST', '/kick_player.php', { room_id: roomId, user_id: userId });
        },
        startGame: function (roomId) {
            return request('POST', '/start_game.php', { room_id: roomId });
        },
        getGameState: function (roomId) {
            return request('GET', '/game_state.php?room_id=' + roomId);
        },
        poll: function (roomId, lastPhase, lastRound, lastMsgId) {
            var q = 'room_id=' + roomId + '&last_phase=' + encodeURIComponent(lastPhase || '') + '&last_round=' + (lastRound || 0) + '&last_msg_id=' + (lastMsgId || 0);
            return request('GET', '/game_poll.php?' + q);
        },
        gameReady: function (gameId) {
            return request('POST', '/game_ready.php', { game_id: gameId });
        },
        revealAttr: function (gameId, attributeKey) {
            return request('POST', '/game_reveal.php', { game_id: gameId, attribute_key: attributeKey });
        },
        advancePhase: function (gameId) {
            return request('POST', '/game_advance.php', { game_id: gameId });
        },
        castVote: function (gameId, targetId) {
            return request('POST', '/game_vote.php', { game_id: gameId, target_id: targetId });
        },
        getMessages: function (roomId, lastId) {
            return request('GET', '/game_chat.php?room_id=' + roomId + '&last_id=' + (lastId || 0));
        },
        sendMessage: function (roomId, text) {
            return request('POST', '/game_chat.php', { room_id: roomId, text: text });
        },
        restartGame: function (roomId) {
            return request('POST', '/game_restart.php', { room_id: roomId });
        },
        getProfile: function () {
            return request('GET', '/profile_update.php');
        },
        updateProfile: function (data) {
            return request('POST', '/profile_update.php', data);
        },
        getStats: function () {
            return request('GET', '/profile_stats.php');
        },
        uploadAvatar: function (file) {
            var form = new FormData();
            form.append('avatar', file);
            return fetch(base + '/avatar.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: form
            }).then(function (res) { return res.json(); }).catch(function (e) {
                if (console && console.error) console.error('Avatar upload', e);
                return { success: false, error: 'Ошибка загрузки' };
            });
        }
    };
})();