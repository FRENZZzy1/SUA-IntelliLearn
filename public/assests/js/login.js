function createParticles() {
    const container = document.getElementById('particles');
    const colors = ['#1B5E20', '#2E7D32', '#FDD835', '#FFEB3B', '#4CAF50'];

    for (let i = 0; i < 30; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.top = Math.random() * 100 + '%';
        particle.style.width = Math.random() * 8 + 4 + 'px';
        particle.style.height = particle.style.width;
        particle.style.background = colors[Math.floor(Math.random() * colors.length)];
        particle.style.animationDelay = Math.random() * 15 + 's';
        particle.style.animationDuration = Math.random() * 10 + 10 + 's';
        container.appendChild(particle);
    }
}

createParticles();

function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}

document.querySelectorAll('.input-group input').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
    });
    input.addEventListener('blur', function() {
        this.parentElement.classList.remove('focused');
    });
});

// Forgot password instructions popup
const forgotPasswordLink = document.getElementById('forgotPasswordLink');
const forgotPasswordModal = document.getElementById('forgotPasswordModal');
const forgotPasswordClose = document.getElementById('forgotPasswordClose');

function openForgotPasswordModal(event) {
    event.preventDefault();
    forgotPasswordModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeForgotPasswordModal() {
    forgotPasswordModal.classList.remove('active');
    document.body.style.overflow = '';
}

forgotPasswordLink.addEventListener('click', openForgotPasswordModal);
forgotPasswordClose.addEventListener('click', closeForgotPasswordModal);

forgotPasswordModal.addEventListener('click', function(event) {
    if (event.target === forgotPasswordModal) {
        closeForgotPasswordModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && forgotPasswordModal.classList.contains('active')) {
        closeForgotPasswordModal();
    }
});

document.getElementById('togglePasswordBtn').addEventListener('click', togglePassword);


/* Terms, Privacy Notice, and AI Use modal */
const legalModal = document.getElementById('legalModal');
const legalModalClose = document.getElementById('legalModalClose');
const legalModalUnderstand = document.getElementById('legalModalUnderstand');
const legalTabs = document.querySelectorAll('[data-legal-tab]');
const legalSections = document.querySelectorAll('[data-legal-content]');
const legalLinks = document.querySelectorAll('[data-legal-section]');
const legalModalTitle = document.getElementById('legalModalTitle');
const legalModalIcon = document.getElementById('legalModalIcon');

const legalMeta = {
    terms: {
        title: 'Terms & Conditions',
        icon: 'fa-file-contract'
    },
    privacy: {
        title: 'Privacy Notice',
        icon: 'fa-shield-halved'
    },
    ai: {
        title: 'Use of AI',
        icon: 'fa-robot'
    }
};

function setLegalSection(section) {
    const selected = legalMeta[section] ? section : 'terms';

    legalTabs.forEach(tab => {
        const active = tab.dataset.legalTab === selected;
        tab.classList.toggle('active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    legalSections.forEach(content => {
        content.classList.toggle('active', content.dataset.legalContent === selected);
    });

    legalModalTitle.textContent = legalMeta[selected].title;
    legalModalIcon.innerHTML = '<i class="fas ' + legalMeta[selected].icon + '"></i>';
}

function openLegalModal(section = 'terms') {
    setLegalSection(section);
    legalModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLegalModal() {
    legalModal.classList.remove('active');
    document.body.style.overflow = '';
}

legalLinks.forEach(link => {
    link.addEventListener('click', function() {
        openLegalModal(this.dataset.legalSection);
    });
});

legalTabs.forEach(tab => {
    tab.addEventListener('click', function() {
        setLegalSection(this.dataset.legalTab);
    });
});

legalModalClose.addEventListener('click', closeLegalModal);
legalModalUnderstand.addEventListener('click', closeLegalModal);

legalModal.addEventListener('click', function(event) {
    if (event.target === legalModal) {
        closeLegalModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && legalModal.classList.contains('active')) {
        closeLegalModal();
    }
});
