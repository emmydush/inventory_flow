document.addEventListener('DOMContentLoaded', () => {
    setupAuthTabs();
    setupFormSubmissions();
    setupSocialButtons();
});

function setupAuthTabs() {
    const tabs = document.querySelectorAll('.auth-tab');
    const forms = document.querySelectorAll('.auth-form');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabName = tab.dataset.tab;
            
            // Update active tab with animation
            tabs.forEach(t => {
                t.classList.remove('active');
                // Add scale animation to inactive tabs
                t.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    t.style.transform = '';
                }, 150);
            });
            tab.classList.add('active');
            
            // Animate tab activation
            tab.style.transform = 'scale(1.05)';
            setTimeout(() => {
                tab.style.transform = '';
            }, 150);
            
            // Show corresponding form with enhanced animation
            forms.forEach(form => {
                form.classList.remove('active');
                form.classList.remove('slide-in-left');
                form.classList.remove('slide-in-right');
            });
            
            const targetForm = document.getElementById(tabName + 'Form');
            
            // Add staggered animation for form elements
            setTimeout(() => {
                targetForm.classList.add('active');
                
                // Add animation class based on direction
                if (tabName === 'login') {
                    targetForm.classList.add('slide-in-left');
                } else {
                    targetForm.classList.add('slide-in-right');
                }
                
                // Animate form elements sequentially
                const formElements = targetForm.querySelectorAll('h2, .form-subtitle, .form-group, .form-options, .btn, .divider, .social-login, .form-footer');
                formElements.forEach((el, index) => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(10px)';
                    setTimeout(() => {
                        el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0)';
                    }, 100 + (index * 50));
                });
                
                // Remove animation classes after transition
                setTimeout(() => {
                    targetForm.classList.remove('slide-in-left');
                    targetForm.classList.remove('slide-in-right');
                }, 300);
            }, 100);
        });
    });
    
    // Handle switch links
    document.querySelectorAll('.switch-auth').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const tabName = link.dataset.tab;
            
            // Add bounce effect to link
            link.style.transform = 'scale(0.95)';
            setTimeout(() => {
                link.style.transform = '';
            }, 150);
            
            // Activate the corresponding tab
            tabs.forEach(tab => {
                if (tab.dataset.tab === tabName) {
                    tab.click();
                }
            });
        });
    });
}

function setupFormSubmissions() {
    // Login form submission
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const username = document.getElementById('loginUsername').value;
        const password = document.getElementById('loginPassword').value;
        
        try {
            showLoading(true, 'login');
            
            // Add shake animation to button
            const loginButton = document.querySelector('#loginForm .btn');
            loginButton.style.transform = 'translateX(0)';
            
            // API call to login endpoint
            const response = await fetch('/api/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Add success animation
                loginButton.style.background = 'var(--success)';
                showToast('Login successful! Redirecting...', 'success');
                
                // Redirect to dashboard after delay
                setTimeout(() => {
                    window.location.href = '/';
                }, 1500);
            } else {
                // Add error shake animation
                loginButton.style.animation = 'shake 0.5s';
                setTimeout(() => {
                    loginButton.style.animation = '';
                }, 500);
                showToast(result.error || 'Login failed', 'error');
            }
        } catch (error) {
            console.error('Login error:', error);
            const loginButton = document.querySelector('#loginForm .btn');
            loginButton.style.animation = 'shake 0.5s';
            setTimeout(() => {
                loginButton.style.animation = '';
            }, 500);
            showToast('An error occurred during login', 'error');
        } finally {
            showLoading(false, 'login');
        }
    });
    
    // Register form submission
    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const fullName = document.getElementById('registerFullName').value;
        const username = document.getElementById('registerUsername').value;
        const email = document.getElementById('registerEmail').value;
        const password = document.getElementById('registerPassword').value;
        const confirmPassword = document.getElementById('registerConfirmPassword').value;
        
        // Validate passwords match
        if (password !== confirmPassword) {
            showToast('Passwords do not match', 'error');
            // Add shake animation to confirm password field
            const confirmPasswordField = document.getElementById('registerConfirmPassword');
            confirmPasswordField.style.animation = 'shake 0.5s';
            setTimeout(() => {
                confirmPasswordField.style.animation = '';
            }, 500);
            return;
        }
        
        try {
            showLoading(true, 'register');
            
            // Add animation to register button
            const registerButton = document.querySelector('#registerForm .btn');
            registerButton.style.transform = 'translateX(0)';
            
            // API call to register endpoint
            const response = await fetch('/api/auth/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    full_name: fullName,
                    username: username,
                    email: email,
                    password: password
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Add success animation
                registerButton.style.background = 'var(--success)';
                showToast('Account created successfully! Please login.', 'success');
                
                // Switch to login tab after delay
                setTimeout(() => {
                    document.querySelector('.auth-tab[data-tab="login"]').click();
                }, 1500);
            } else {
                // Add error shake animation
                registerButton.style.animation = 'shake 0.5s';
                setTimeout(() => {
                    registerButton.style.animation = '';
                }, 500);
                showToast(result.error || 'Registration failed', 'error');
            }
        } catch (error) {
            console.error('Registration error:', error);
            const registerButton = document.querySelector('#registerForm .btn');
            registerButton.style.animation = 'shake 0.5s';
            setTimeout(() => {
                registerButton.style.animation = '';
            }, 500);
            showToast('An error occurred during registration', 'error');
        } finally {
            showLoading(false, 'register');
        }
    });
}

function setupSocialButtons() {
    const socialButtons = document.querySelectorAll('.social-btn');
    socialButtons.forEach(button => {
        button.addEventListener('mouseenter', () => {
            button.style.transform = 'translateY(-5px)';
        });
        
        button.addEventListener('mouseleave', () => {
            button.style.transform = 'translateY(0)';
        });
        
        button.addEventListener('click', () => {
            button.style.transform = 'scale(0.9)';
            setTimeout(() => {
                button.style.transform = '';
                showToast('Social login not implemented yet', 'error');
            }, 150);
        });
    });
}

function showLoading(show, formType) {
    const loginButton = document.querySelector('#loginForm .btn');
    const registerButton = document.querySelector('#registerForm .btn');
    
    if (show) {
        if (formType === 'login') {
            loginButton.innerHTML = '<div class="spinner"></div> Signing In...';
            loginButton.disabled = true;
        } else {
            registerButton.innerHTML = '<div class="spinner"></div> Creating Account...';
            registerButton.disabled = true;
        }
    } else {
        loginButton.innerHTML = 'Sign In';
        loginButton.disabled = false;
        registerButton.innerHTML = 'Create Account';
        registerButton.disabled = false;
    }
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');
    
    toast.className = 'toast ' + type;
    toastMessage.textContent = message;
    toast.classList.add('active');
    
    // Add animation to toast
    toast.style.animation = 'fadeInOut 3s forwards';
    
    setTimeout(() => {
        toast.classList.remove('active');
        toast.style.animation = '';
    }, 3000);
}

// Add CSS for additional animations that might not be in the main CSS file
const style = document.createElement('style');
style.innerHTML = `
    @keyframes pulse {
        0% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4);
        }
        70% {
            transform: scale(1.05);
            box-shadow: 0 0 0 10px rgba(255, 255, 255, 0);
        }
        100% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(255, 255, 255, 0);
        }
    }
    
    @keyframes tabSlide {
        from {
            transform: scaleX(0);
        }
        to {
            transform: scaleX(1);
        }
    }
`;
document.head.appendChild(style);