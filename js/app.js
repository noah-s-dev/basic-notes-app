// Notes Pro - Modern JavaScript Enhancements

// Global variables
let autoSaveTimer;
let isDirty = false;

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// Main initialization function
function initializeApp() {
    // Add fade-in animations to cards
    addFadeInAnimations();
    
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize form enhancements
    initializeFormEnhancements();
    
    // Initialize file uploads
    initializeFileUploads();
    
    // Initialize auto-save
    initializeAutoSave();
    
    // Initialize keyboard shortcuts
    initializeKeyboardShortcuts();
    
    // Initialize theme switcher
    initializeThemeSwitcher();
}

// Add fade-in animations to cards
function addFadeInAnimations() {
    const cards = document.querySelectorAll('.card, .feature-card, .pricing-card');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    
    cards.forEach(card => observer.observe(card));
}

// Initialize Bootstrap tooltips
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Initialize form enhancements
function initializeFormEnhancements() {
    // Add floating labels
    const formControls = document.querySelectorAll('.form-control, .form-select');
    formControls.forEach(control => {
        if (control.value) {
            control.classList.add('has-value');
        }
        
        control.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        control.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
            if (this.value) {
                this.classList.add('has-value');
            } else {
                this.classList.remove('has-value');
            }
        });
    });
    
    // Add character counters
    const textAreas = document.querySelectorAll('textarea[maxlength], input[maxlength]');
    textAreas.forEach(textarea => {
        const counter = document.createElement('div');
        counter.className = 'form-text text-end';
        counter.id = textarea.id + '_counter';
        textarea.parentNode.appendChild(counter);
        
        function updateCounter() {
            const remaining = textarea.maxLength - textarea.value.length;
            counter.textContent = `${remaining} characters remaining`;
            counter.className = `form-text text-end ${remaining < 10 ? 'text-danger' : 'text-muted'}`;
        }
        
        textarea.addEventListener('input', updateCounter);
        updateCounter();
    });
}

// Initialize file uploads
function initializeFileUploads() {
    const uploadArea = document.getElementById('uploadArea');
    if (!uploadArea) return;
    
    // Drag and drop functionality
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        handleFileUpload(files);
    });
    
    // Click to upload
    uploadArea.addEventListener('click', function() {
        document.getElementById('attachments').click();
    });
    
    // File input change
    document.getElementById('attachments').addEventListener('change', function(e) {
        handleFileUpload(e.target.files);
    });
}

// Handle file upload
function handleFileUpload(files) {
    const preview = document.getElementById('filePreview');
    if (!preview) return;
    
    Array.from(files).forEach(file => {
        if (validateFile(file)) {
            const fileElement = createFilePreview(file);
            preview.appendChild(fileElement);
        }
    });
}

// Validate file
function validateFile(file) {
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
    
    if (file.size > maxSize) {
        showNotification('File too large. Maximum size is 5MB.', 'error');
        return false;
    }
    
    if (!allowedTypes.includes(file.type)) {
        showNotification('File type not allowed.', 'error');
        return false;
    }
    
    return true;
}

// Create file preview
function createFilePreview(file) {
    const div = document.createElement('div');
    div.className = 'file-preview-item d-inline-block me-2 mb-2';
    
    if (file.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.className = 'file-preview';
        div.appendChild(img);
    } else {
        const icon = document.createElement('i');
        icon.className = 'bi bi-file-earmark display-6 text-muted';
        div.appendChild(icon);
    }
    
    const name = document.createElement('div');
    name.className = 'small text-muted';
    name.textContent = file.name;
    div.appendChild(name);
    
    return div;
}

// Initialize auto-save
function initializeAutoSave() {
    const contentEditor = document.getElementById('contentEditor');
    if (!contentEditor) return;
    
    contentEditor.addEventListener('input', function() {
        isDirty = true;
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(() => {
            saveDraft();
        }, 3000); // Auto-save after 3 seconds of inactivity
    });
}

// Save draft function
function saveDraft() {
    const content = document.getElementById('contentEditor').innerHTML;
    const title = document.getElementById('title').value;
    
    if (!content.trim() && !title.trim()) return;
    
    localStorage.setItem('note_draft', JSON.stringify({
        title: title,
        content: content,
        timestamp: new Date().toISOString()
    }));
    
    isDirty = false;
    showNotification('Draft saved automatically', 'success');
}

// Load draft function
function loadDraft() {
    const draft = localStorage.getItem('note_draft');
    if (draft) {
        const data = JSON.parse(draft);
        document.getElementById('title').value = data.title || '';
        document.getElementById('contentEditor').innerHTML = data.content || '';
        showNotification('Draft loaded', 'info');
    }
}

// Initialize keyboard shortcuts
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveDraft();
        }
        
        // Ctrl/Cmd + Enter to submit form
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const form = document.querySelector('form');
            if (form) {
                form.submit();
            }
        }
        
        // Escape to clear form
        if (e.key === 'Escape') {
            if (confirm('Are you sure you want to clear the form?')) {
                clearForm();
            }
        }
    });
}

// Clear form
function clearForm() {
    const form = document.querySelector('form');
    if (form) {
        form.reset();
        const contentEditor = document.getElementById('contentEditor');
        if (contentEditor) {
            contentEditor.innerHTML = '';
        }
        isDirty = false;
        showNotification('Form cleared', 'info');
    }
}

// Initialize theme switcher
function initializeThemeSwitcher() {
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) return;
    
    // Load saved theme
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.body.className = savedTheme + '-theme';
    
    themeToggle.addEventListener('change', function() {
        const theme = this.checked ? 'dark' : 'light';
        document.body.className = theme + '-theme';
        localStorage.setItem('theme', theme);
        showNotification(`Switched to ${theme} theme`, 'success');
    });
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Format text in rich editor
function formatText(command) {
    document.execCommand(command, false, null);
    document.getElementById('contentEditor').focus();
}

// Insert list in rich editor
function insertList(type) {
    document.execCommand('insert' + (type === 'ul' ? 'Unordered' : 'Ordered') + 'List', false, null);
    document.getElementById('contentEditor').focus();
}

// Insert link in rich editor
function insertLink() {
    const url = prompt('Enter URL:');
    if (url) {
        document.execCommand('createLink', false, url);
    }
    document.getElementById('contentEditor').focus();
}

// Insert template
function insertTemplate(type) {
    const templates = {
        meeting: `
            <h3>Meeting Notes</h3>
            <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
            <p><strong>Attendees:</strong></p>
            <ul>
                <li></li>
            </ul>
            <p><strong>Agenda:</strong></p>
            <ol>
                <li></li>
            </ol>
            <p><strong>Action Items:</strong></p>
            <ul>
                <li></li>
            </ul>
        `,
        todo: `
            <h3>To-Do List</h3>
            <ul>
                <li><input type="checkbox"> Task 1</li>
                <li><input type="checkbox"> Task 2</li>
                <li><input type="checkbox"> Task 3</li>
            </ul>
        `,
        project: `
            <h3>Project Overview</h3>
            <p><strong>Project Name:</strong></p>
            <p><strong>Description:</strong></p>
            <p><strong>Goals:</strong></p>
            <ul>
                <li></li>
            </ul>
            <p><strong>Timeline:</strong></p>
            <p><strong>Resources:</strong></p>
        `
    };
    
    const contentEditor = document.getElementById('contentEditor');
    if (contentEditor && templates[type]) {
        contentEditor.innerHTML = templates[type];
        contentEditor.focus();
        showNotification('Template inserted', 'success');
    }
}

// Update note statistics
function updateNoteStats() {
    const content = document.getElementById('contentEditor').innerText || '';
    const words = content.trim() ? content.trim().split(/\s+/).length : 0;
    const chars = content.length;
    const readTime = Math.ceil(words / 200); // Average reading speed
    
    document.getElementById('wordCount').textContent = words;
    document.getElementById('charCount').textContent = chars;
    document.getElementById('readTime').textContent = readTime;
}

// Initialize note statistics
if (document.getElementById('contentEditor')) {
    const contentEditor = document.getElementById('contentEditor');
    contentEditor.addEventListener('input', updateNoteStats);
    updateNoteStats();
}

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Add loading states to buttons
document.querySelectorAll('button[type="submit"]').forEach(button => {
    button.addEventListener('click', function() {
        if (this.form && this.form.checkValidity()) {
            this.innerHTML = '<span class="loading"></span> Processing...';
            this.disabled = true;
        }
    });
});

// Export functions for global use
window.NotesApp = {
    showNotification,
    saveDraft,
    loadDraft,
    formatText,
    insertList,
    insertLink,
    insertTemplate,
    updateNoteStats
}; 