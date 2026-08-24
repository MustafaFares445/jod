<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JOD Firebase Push Test</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 760px; margin: 40px auto; padding: 0 20px; }
        label { display: block; margin-top: 16px; font-weight: 600; }
        input, textarea, select, button { width: 100%; box-sizing: border-box; margin-top: 6px; padding: 10px; font: inherit; }
        textarea { min-height: 120px; }
        button { margin-top: 20px; cursor: pointer; }
        pre { white-space: pre-wrap; padding: 16px; background: #f5f5f5; border-radius: 8px; }
    </style>
</head>
<body>
    <h1>JOD Firebase Push Test</h1>
    <p>Paste an FCM registration token from the browser or app, then send a direct Firebase test notification.</p>

    <form id="push-test-form">
        <label for="fcmToken">FCM token</label>
        <textarea id="fcmToken" name="fcmToken" required></textarea>

        <label for="platform">Platform</label>
        <select id="platform" name="platform">
            <option value="web">Web</option>
            <option value="android">Android</option>
            <option value="ios">iOS</option>
            <option value="mobile">Mobile / unknown</option>
        </select>

        <label for="title">Title</label>
        <input id="title" name="title" value="JOD Firebase browser test">

        <label for="body">Body</label>
        <textarea id="body" name="body">If you received this notification, Firebase push delivery is working.</textarea>

        <button type="submit">Send Firebase test push</button>
    </form>

    <h2>Response</h2>
    <pre id="result">No request sent yet.</pre>

    <script>
        const form = document.getElementById('push-test-form');
        const result = document.getElementById('result');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            result.textContent = 'Sending…';

            const payload = Object.fromEntries(new FormData(form).entries());

            try {
                const response = await fetch('/api/v1/firebase/test-push', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await response.json();
                result.textContent = JSON.stringify({
                    httpStatus: response.status,
                    ...data,
                }, null, 2);
            } catch (error) {
                result.textContent = String(error);
            }
        });
    </script>
</body>
</html>
