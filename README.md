# Notes Pro - Professional Note Taking Application

A comprehensive, commercial-grade note-taking application built with modern web technologies. Features a beautiful, responsive UI with enhanced user experience and advanced collaboration tools.

## 🛠️ Technologies Used

### Backend Technologies
- **PHP 8.0+** - Server-side scripting language
- **MySQL 8.0+** - Relational database management system
- **Apache/Nginx** - Web server

### Frontend Technologies
- **HTML5** - Semantic markup
- **CSS3** - Modern styling with custom properties and animations
- **JavaScript (ES6+)** - Client-side functionality and interactions
- **Bootstrap 5.1.3** - Responsive UI framework
- **Bootstrap Icons** - Icon library

### Development Tools
- **Composer** - Dependency management (optional)
- **Git** - Version control
- **XAMPP/WAMP** - Local development environment

## 📋 Project Overview

Notes Pro is a professional note-taking application designed for modern teams and organizations. It provides a comprehensive solution for creating, organizing, and collaborating on notes with advanced features like file attachments, version history, and subscription-based premium features.

The application follows modern web development practices with a focus on:
- **Security**: CSRF protection, password hashing, input sanitization
- **Performance**: Optimized database queries, efficient file handling
- **User Experience**: Intuitive interface, responsive design, smooth animations
- **Scalability**: Modular architecture, feature flags, extensible design

## ✨ Key Features

### 🎨 Modern UI/UX
- **Beautiful Design**: Modern gradient backgrounds, smooth animations, professional styling
- **Responsive Layout**: Optimized for desktop, tablet, and mobile devices
- **Dark/Light Themes**: Toggle between themes with automatic preference saving
- **Interactive Elements**: Hover effects, loading states, and visual feedback

### 📝 Core Features
- **User Authentication**: Secure login/registration with session management
- **Note Management**: Create, edit, delete, and organize notes with rich formatting
- **Rich Text Editor**: WYSIWYG editor with toolbar and formatting options
- **Categories & Tags**: Color-coded categories and tags for organization
- **Advanced Search**: Real-time search with filters and sorting options
- **File Attachments**: Drag-and-drop file uploads with preview
- **Note Sharing**: Share notes with other users and collaboration
- **Version History**: Track changes and restore previous versions
- **User Preferences**: Customizable themes, fonts, and settings
- **Activity Logging**: Track user actions and analytics

### ⭐ Premium Features
- **Subscription Plans**: Free, Basic, Premium, and Enterprise tiers
- **Advanced Analytics**: Usage statistics and insights with visual charts
- **Collaboration Tools**: Real-time collaboration features
- **API Access**: RESTful API for integrations
- **Admin Panel**: Comprehensive administration tools
- **Priority Support**: Dedicated customer support

## 👥 User Roles

### **Free Users**
- Create up to 50 notes
- 100 MB storage limit
- Basic search functionality
- Category organization
- Standard support

### **Basic Users ($4.99/month)**
- Create up to 500 notes
- 1 GB storage limit
- Tags and file attachments
- Note sharing capabilities
- Email support

### **Premium Users ($9.99/month)**
- Create up to 5,000 notes
- 10 GB storage limit
- Advanced collaboration tools
- Version history tracking
- Advanced search capabilities
- Priority email support

### **Enterprise Users ($19.99/month)**
- Unlimited notes
- 100 GB storage limit
- All Premium features
- Admin panel access
- Advanced analytics
- API access
- Priority phone support

## 📁 Project Structure

```
basic-notes-app/
├── index.php                 # Main entry point (redirects to landing)
├── landing.php               # Marketing landing page with features/pricing
├── login.php                 # User authentication page
├── dashboard.php             # Main dashboard with integrated search
├── add_note.php             # Note creation with rich editor
├── edit_note.php            # Note editing functionality
├── delete_note.php          # Note deletion with confirmation
├── profile.php              # User profile (under development)
├── settings.php             # User settings and preferences
├── subscription.php         # Subscription management
├── register.php             # User registration
├── logout.php               # Logout functionality
├── includes/
│   └── config.php           # Configuration and helper functions
├── css/
│   └── style.css            # Custom styling and animations
├── js/
│   └── app.js               # JavaScript functionality
├── sql/
│   └── setup.sql            # Database schema and sample data
├── uploads/                 # File upload directory
└── README.md                # Project documentation
```

## 🚀 Setup Instructions

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- Composer (optional, for dependency management)

### Installation Steps

1. **Clone or Download the Project**
   ```bash
   # If using Git
   git clone <repository-url>
   cd basic-notes-app
   
   # Or download and extract to your web server directory
   ```

2. **Database Setup**
   ```sql
   -- Create a new MySQL database
   CREATE DATABASE notes_app;
   
   -- Import the database schema
   mysql -u username -p notes_app < sql/setup.sql
   ```

3. **Configuration**
   - Copy `includes/config.php` and update database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'notes_app');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

4. **File Permissions**
   ```bash
   chmod 755 uploads/
   chmod 644 includes/config.php
   ```

5. **Web Server Configuration**
   - Point your web server to the project directory
   - Ensure the `uploads/` directory is writable
   - Configure URL rewriting if needed

6. **Access the Application**
   - Navigate to your web server URL
   - Register a new account or use the demo account:
     - Username: `demo_user`
     - Password: `password123`

## 📖 Usage

### For End Users

1. **Getting Started**
   - Register a new account or sign in
   - Complete your profile setup
   - Choose a subscription plan

2. **Creating Notes**
   - Navigate to "Add Note"
   - Use the rich text editor with formatting options
   - Add categories and tags for organization
   - Attach files using drag-and-drop
   - Set note options (public, pinned, favorite)

3. **Organizing Content**
   - Use categories to group related notes
   - Apply tags for detailed classification
   - Pin important notes for quick access
   - Mark favorites for easy retrieval
   - Archive old notes to reduce clutter

4. **Collaboration**
   - Share notes with team members
   - Enable real-time editing
   - Track changes and comments
   - Manage access permissions

### For Administrators

1. **Subscription Management**
   - Monitor user subscriptions
   - Manage billing and payments
   - Handle upgrade/downgrade requests

2. **User Management**
   - View user activities and analytics
   - Monitor system usage
   - Handle support requests

3. **System Maintenance**
   - Database optimization
   - File storage management
   - Performance monitoring

## 🎯 Intended Use

### **Demo and Learning**
- Perfect for demonstrating modern web application development
- Excellent learning resource for PHP, MySQL, and frontend technologies
- Shows best practices in web development and UI/UX design

### **Personal Use**
- Personal note-taking and organization
- Learning and experimentation
- Portfolio projects

### **Commercial Use**
- **Requires Pro License** from RiverTheme.com
- Custom development and modifications
- Production deployments
- White-label solutions
- Enterprise integrations

### **Educational Use**
- Classroom demonstrations
- Code review and analysis
- Teaching web development concepts
- Student projects and assignments

## 📄 License

**License for RiverTheme**

RiverTheme makes this project available for demo, instructional, and personal use. You can ask for or buy a license from [RiverTheme.com](https://RiverTheme.com) if you want a pro website, sophisticated features, or expert setup and assistance. A Pro license is needed for production deployments, customizations, and commercial use.

**Disclaimer**

The free version is offered "as is" with no warranty and might not function on all devices or browsers. It might also have some coding or security flaws. For additional information or to get a Pro license, please get in touch with [RiverTheme.com](https://RiverTheme.com).

---

**Notes Pro** - Professional note-taking for modern teams.

**Developed by RiverTheme** - Professional web development solutions. 