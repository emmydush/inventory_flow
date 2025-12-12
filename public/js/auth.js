document.addEventListener('DOMContentLoaded', () => {
    // On login page, we don't check session automatically to prevent flickering
    // Session checking is handled by form submissions
    setupAuthTabs();
    setupFormSubmissions();
    setupSocialButtons();
});

// Flag to prevent continuous redirects
let redirecting = false;

// Removed session checking on login page to prevent flickering
// Session status is determined by login form submission

function setupAuthTabs() {
    const tabs = document.querySelectorAll('.auth-tab');
    const forms = document.querySelectorAll('.auth-form');
    
    // Simple function to switch to a specific form
    function switchToForm(formType) {
        // Update active tab
        tabs.forEach(tab => {
            if (tab.dataset.tab === formType) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        
        // Show the correct form
        forms.forEach(form => {
            if (form.id === formType + 'Form') {
                form.classList.add('active');
            } else {
                form.classList.remove('active');
            }
        });
    }
    
    // Tab click handlers
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabName = tab.dataset.tab;
            switchToForm(tabName);
        });
    });
    
    // Handle switch links (the "Sign up" and "Sign in" links)
    const switchAuthLinks = document.querySelectorAll('.switch-auth');
    switchAuthLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const tabName = link.dataset.tab;
            switchToForm(tabName);
        });
    });
}

function setupFormSubmissions() {
    // Login form submission
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const username = document.getElementById('loginUsername').value.trim();
        const password = document.getElementById('loginPassword').value;

        if (!username) {
            addFieldError('loginUsername', 'Username or email is required');
            return;
        }

        if (!password) {
            addFieldError('loginPassword', 'Password is required');
            return;
        }

        clearFieldErrors('loginForm');

        try {
            showLoading(true, 'login');

            const response = await fetch('/api/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',  // Ensure credentials are sent with the request
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            });

            const result = await response.json();

            if (result.success) {
                const loginButton = document.querySelector('#loginForm .btn');
                loginButton.style.background = 'var(--success)';
                showToast('Login successful! Redirecting...', 'success');

                setTimeout(() => {
                    window.location.href = '/';
                }, 1500);
            } else {
                addFieldError('loginPassword', result.error || 'Login failed');
                showToast(result.error || 'Login failed', 'error');
            }
        } catch (error) {
            console.error('Login error:', error);
            showToast('An error occurred during login', 'error');
        } finally {
            showLoading(false, 'login');
        }
    });
    
    // Register form submission
    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const fullName = document.getElementById('registerFullName').value.trim();
        const username = document.getElementById('registerUsername').value.trim();
        const email = document.getElementById('registerEmail').value.trim();
        const password = document.getElementById('registerPassword').value;
        const confirmPassword = document.getElementById('registerConfirmPassword').value;

        // Debug: Log form values
        console.log('Registration form values:', {
            fullName,
            username,
            email,
            password,
            confirmPassword
        });

        clearFieldErrors('registerForm');

        if (!fullName) {
            addFieldError('registerFullName', 'Full name is required');
            return;
        }

        if (!username) {
            addFieldError('registerUsername', 'Username is required');
            return;
        }

        if (username.length < 3) {
            addFieldError('registerUsername', 'Username must be at least 3 characters');
            return;
        }

        if (!email) {
            addFieldError('registerEmail', 'Email is required');
            return;
        }

        if (!isValidEmail(email)) {
            addFieldError('registerEmail', 'Please enter a valid email address');
            return;
        }

        if (!password) {
            addFieldError('registerPassword', 'Password is required');
            return;
        }

        if (password.length < 6) {
            addFieldError('registerPassword', 'Password must be at least 6 characters');
            return;
        }

        if (!confirmPassword) {
            addFieldError('registerConfirmPassword', 'Please confirm your password');
            return;
        }

        if (password !== confirmPassword) {
            addFieldError('registerConfirmPassword', 'Passwords do not match');
            return;
        }

        // Debug: Log data being sent
        const requestData = {
            full_name: fullName,
            username: username,
            email: email,
            password: password
        };
        
        console.log('Sending registration request:', requestData);

        try {
            showLoading(true, 'register');

            const response = await fetch('/api/auth/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestData)
            });

            const result = await response.json();
            console.log('Registration response:', result);

            if (result.success) {
                const registerButton = document.querySelector('#registerForm .btn');
                registerButton.style.background = 'var(--success)';
                showToast('Account created successfully! Switching to login...', 'success');

                setTimeout(() => {
                    document.getElementById('registerForm').reset();
                    document.querySelector('.auth-tab[data-tab="login"]').click();
                }, 1500);
            } else {
                showToast(result.error || 'Registration failed', 'error');
            }
        } catch (error) {
            console.error('Registration error:', error);
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

function addFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    field.classList.add('error');

    let errorElement = field.parentElement.querySelector('.field-error');
    if (!errorElement) {
        errorElement = document.createElement('small');
        errorElement.className = 'field-error';
        field.parentElement.appendChild(errorElement);
    }

    errorElement.textContent = message;
    errorElement.style.display = 'block';

    field.addEventListener('input', function() {
        field.classList.remove('error');
        if (errorElement) errorElement.style.display = 'none';
    }, { once: true });

    field.focus();
}

function clearFieldErrors(formId) {
    const form = document.getElementById(formId);
    form.querySelectorAll('.error').forEach(field => {
        field.classList.remove('error');
    });
    form.querySelectorAll('.field-error').forEach(error => {
        error.style.display = 'none';
    });
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Add CSS for field errors and additional animations
const style = document.createElement('style');
style.innerHTML = `
    .field-error {
        display: none;
        color: var(--danger);
        font-size: 0.8rem;
        margin-top: 0.25rem;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .form-group input.error {
        border-color: var(--danger);
        background: rgba(239, 68, 68, 0.05);
    }

    .form-group input.error:focus {
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
    }

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
