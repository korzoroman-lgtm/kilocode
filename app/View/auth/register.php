<div class="container" style="max-width: 400px; padding: 40px 20px;">
    <div class="card">
        <div class="card-header">
            <h1 style="text-align: center; margin: 0;">Create Account</h1>
        </div>
        <div class="card-body">
            <form method="POST" action="/register">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-group">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-input" required minlength="2" autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input" required minlength="8">
                    <p class="form-help">At least 8 characters</p>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password_confirm">Confirm Password</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-input" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
            </form>
            
            <hr style="margin: 20px 0; border-color: var(--border-color);">
            
            <p style="text-align: center; color: var(--text-muted);">
                Already have an account?
                <a href="/login">Login</a>
            </p>
        </div>
    </div>
</div>
