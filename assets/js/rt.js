(function () {
    'use strict';
    var roomId = null;
    var lastPhase = '';
    var lastRound = 0;
    var lastMsgId = 0;
    var intervalId = null;
    var chatIntervalId = null;
    var POLL_MS = 1000;
    var CHAT_POLL_MS = 1000;
    var callbacks = { state_change: [], new_messages: [] };
    function on(ev, fn) {
        if (callbacks[ev]) callbacks[ev].push(fn);
    }
    function emit(ev, data) {
        (callbacks[ev] || []).forEach(function (fn) { try { fn(data); } catch (e) { if (console && console.error) console.error('RT callback', e); } });
    }
    function tick() {
        if (!roomId || !window.Api) return;
        window.Api.poll(roomId, lastPhase, lastRound, lastMsgId).then(function (r) {
            if (r.changed) {
                lastPhase = r.phase || lastPhase;
                lastRound = r.round_num !== undefined ? r.round_num : lastRound;
                emit('state_change', r);
            }
            if (r.has_new_messages) {
                emit('new_messages', {});
            }
        }).catch(function (e) {
            if (console && console.error) console.error('Poll error', e);
        });
    }
    function chatTick() {
        if (!roomId || !window.Api) return;
        window.Api.getMessages(roomId, lastMsgId).then(function (r) {
            if (r.success && r.data && r.data.length) {
                r.data.forEach(function (m) {
                    if (m.id > lastMsgId) lastMsgId = m.id;
                });
                emit('new_messages', r.data);
            }
        }).catch(function (e) {
            if (console && console.error) console.error('Chat poll error', e);
        });
    }
    window.RT = {
        connect: function (id) {
            roomId = id;
            lastPhase = '';
            lastRound = 0;
            lastMsgId = 0;
            if (intervalId) clearInterval(intervalId);
            if (chatIntervalId) clearInterval(chatIntervalId);
            tick();
            intervalId = setInterval(tick, POLL_MS);
            chatIntervalId = setInterval(chatTick, CHAT_POLL_MS);
        },
        disconnect: function () {
            roomId = null;
            if (intervalId) { clearInterval(intervalId); intervalId = null; }
            if (chatIntervalId) { clearInterval(chatIntervalId); chatIntervalId = null; }
        },
        on: on,
        setLastPhase: function (phase, round) {
            lastPhase = phase || lastPhase;
            lastRound = round !== undefined ? round : lastRound;
        },
        setLastMsgId: function (id) {
            if (id > lastMsgId) lastMsgId = id;
        }
    };
})();