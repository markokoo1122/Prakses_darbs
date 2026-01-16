<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - LED Matrix</title>
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
        }
        .auth-container h2 {
            margin-bottom: 20px;
            color: #2196F3;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            background: #222;
            border: 1px solid #444;
            color: white;
            border-radius: 5px;
        }
        .link {
            margin-top: 15px;
            display: block;
            color: #aaa;
            text-decoration: none;
        }
        .link:hover {
            color: white;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h2>Register</h2>
        <form id="registerForm">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="password" required>
            </div>
            <button type="submit">Register</button>
        </form>
        <a href="login.php" class="link">Already have an account? Login</a>
        <a href="public.php" class="link">View Gallery</a>
        <a href="index.php" class="link">Back to Home</a>
    </div>

    <script src="background.js"></script>
    <script>
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            try {
                const res = await fetch('auth.php?action=register', {
                    method: 'POST',
                    body: JSON.stringify({ username, password })
                });
                const data = await res.json();
                
                if (data.status === 'success') {
                    alert('Registration successful! Please login.');
                    window.location.href = 'login.php';
                } else {
                    alert(data.message);
                }
            } catch (err) {
                console.error(err);
                alert('Registration failed');
            }
        });
    </script>
</body>
</html>
