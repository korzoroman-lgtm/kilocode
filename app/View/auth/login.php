<div class="container" style="max-width: 400px; padding: 40px 20px;">
    <div class="card">
        <div class="card-header">
            <h1 style="text-align: center; margin: 0;">Login</h1>
        </div>
        <div class="card-body">
            <form method="POST" action="/login">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-input" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/forgot-password">Forgot password?</a>
            </div>
            
            <hr style="margin: 20px 0; border-color: var(--border-color);">
            
            <p style="text-align: center; color: var(--text-muted);">
                Don't have an account?
                <a href="/register">Sign up</a>
            </p>
        </div>
    </div>
</div>
