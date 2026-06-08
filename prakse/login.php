<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LED Matrix</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .auth-container {
            background: #111;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            width: 300px;
            text-align: center;
            border: 1px solid #333;
            position: relative;
        }
        .auth-container h2 { margin-bottom: 20px; color: #2196F3; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input {
            width: 100%; padding: 10px;
            background: #222; border: 1px solid #444;
            color: white; border-radius: 5px;
        }
        .link { margin-top: 15px; display: block; color: #aaa; text-decoration: none; }
        .link:hover { color: white; }
        .theme-toggle {
            position: absolute; top: 12px; right: 12px;
            background: #1a1a1a; border: 1px solid #333; color: #aaa;
            width: 30px; height: 30px; border-radius: 50%;
            cursor: pointer; font-size: 14px;
            display: flex; align-items: center; justify-content: center;
            padding: 0; margin: 0;
        }
        .theme-toggle:hover { border-color: #aaa; color: #fff; }
    </style>
</head>
<body>
    <div class="auth-container">
        <button class="theme-toggle" id="themeToggle" title="Toggle light/dark mode">☀</button>
        <h2>Login</h2>
        <form id="loginForm">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <a href="register.php" class="link">Create an account</a>
        <a href="public.php" class="link">View Gallery</a>
        <a href="index.php" class="link">Back to Home</a>
    </div>

    <script src="background.js"></script>
    <script>
        const toggle = document.getElementById('themeToggle');
        const root = document.documentElement;
        const saved = localStorage.getItem('theme') || 'dark';
        root.setAttribute('data-theme', saved);
        toggle.textContent = saved === 'dark' ? '☀' : '☾';
        toggle.addEventListener('click', () => {
            const current = root.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            toggle.textContent = next === 'dark' ? '☀' : '☾';
        });
    </script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            try {
                const res = await fetch('auth.php?action=login', {
                    method: 'POST',
                    body: JSON.stringify({ username, password })
                });
                const data = await res.json();
                
                if (data.status === 'success') {
                    window.location.href = 'editor.php';
                } else {
                    alert(data.message);
                }
            } catch (err) {
                console.error(err);
                alert('Login failed');
            }
        });
    </script>
</body>
</html>
