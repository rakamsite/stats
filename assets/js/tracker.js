(function () {
	'use strict';

	if (typeof MyFAStats === 'undefined' || !MyFAStats.endpoint) {
		return;
	}

	var SESSION_TIMEOUT_MS = (parseInt(MyFAStats.sessionTimeout, 10) || 30) * 60 * 1000;
	var HEARTBEAT_MS = (parseInt(MyFAStats.heartbeatInterval, 10) || 20) * 1000;
	var CID_KEY = 'my_fa_stats_cid';
	var SID_KEY = 'my_fa_stats_sid';
	var LAST_SEEN_KEY = 'my_fa_stats_last_seen';
	var ACTIVE_KEY = 'my_fa_stats_active';

	function randomId(prefix) {
		return prefix + '_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
	}

	function setCookie(name, value, days) {
		var expires = '';
		if (days) {
			var date = new Date();
			date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
			expires = '; expires=' + date.toUTCString();
		}
		document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
	}

	function getCookie(name) {
		var parts = document.cookie ? document.cookie.split('; ') : [];
		for (var i = 0; i < parts.length; i++) {
			var cookieParts = parts[i].split('=');
			if (cookieParts[0] === name) {
				return decodeURIComponent(cookieParts.slice(1).join('='));
			}
		}
		return '';
	}

	function readStorage(key) {
		try {
			return window.localStorage.getItem(key) || '';
		} catch (e) {
			return '';
		}
	}

	function writeStorage(key, value) {
		try {
			window.localStorage.setItem(key, value);
		} catch (e) {
			// سکوت.
		}
	}

	function getCid() {
		var cid = getCookie(CID_KEY);
		if (!cid) {
			cid = randomId('cid');
			setCookie(CID_KEY, cid, 180);
		}
		return cid;
	}

	function getSid() {
		var sid = readStorage(SID_KEY) || getCookie(SID_KEY);
		var lastSeen = parseInt(readStorage(LAST_SEEN_KEY), 10);
		var now = Date.now();

		if (!sid || !lastSeen || now - lastSeen > SESSION_TIMEOUT_MS) {
			sid = randomId('sid');
		}

		writeStorage(SID_KEY, sid);
		writeStorage(LAST_SEEN_KEY, String(now));
		setCookie(SID_KEY, sid, 2);

		return sid;
	}

	function getParams() {
		var params = new URLSearchParams(window.location.search);
		var clickId = params.get('gclid') || params.get('gbraid') || params.get('wbraid') || '';
		return {
			utm_source: params.get('utm_source') || '',
			utm_medium: params.get('utm_medium') || '',
			utm_campaign: params.get('utm_campaign') || '',
			utm_term: params.get('utm_term') || '',
			utm_content: params.get('utm_content') || '',
			click_id: clickId
		};
	}

	function buildPayload(eventType, includeReferrer) {
		var payload = {
			event: eventType,
			sid: getSid(),
			cid: getCid(),
			path: window.location.pathname || '/',
			ts: Date.now(),
			tz_offset: new Date().getTimezoneOffset()
		};

		var params = getParams();
		for (var key in params) {
			if (Object.prototype.hasOwnProperty.call(params, key) && params[key]) {
				payload[key] = params[key];
			}
		}

		if (includeReferrer && document.referrer) {
			payload.referrer = document.referrer;
		}

		return payload;
	}

	function send(eventType, includeReferrer, forceBeacon) {
		var payload = buildPayload(eventType, includeReferrer);
		var body = JSON.stringify(payload);
		var headers = { 'Content-Type': 'application/json' };

		if ((forceBeacon || eventType === 'exit') && navigator.sendBeacon) {
			var blob = new Blob([body], { type: 'application/json' });
			navigator.sendBeacon(MyFAStats.endpoint, blob);
			return;
		}

		fetch(MyFAStats.endpoint, {
			method: 'POST',
			credentials: 'omit',
			keepalive: true,
			headers: headers,
			body: body
		}).catch(function () {});
	}

	function markActive() {
		writeStorage(ACTIVE_KEY, String(Date.now()));
	}

	['mousemove', 'keydown', 'touchstart', 'scroll'].forEach(function (evt) {
		window.addEventListener(evt, markActive, { passive: true });
	});

	var firstRef = !readStorage('my_fa_stats_ref_sent');
	send('pageview', firstRef, false);
	if (firstRef) {
		writeStorage('my_fa_stats_ref_sent', '1');
	}

	setInterval(function () {
		if (document.visibilityState !== 'visible') {
			return;
		}
		var lastActive = parseInt(readStorage(ACTIVE_KEY), 10) || 0;
		if (Date.now() - lastActive > HEARTBEAT_MS * 2) {
			return;
		}
		send('heartbeat', false, false);
	}, HEARTBEAT_MS);

	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'hidden') {
			send('exit', false, true);
		}
	});

	window.addEventListener('pagehide', function () {
		send('exit', false, true);
	});

	markActive();
})();
