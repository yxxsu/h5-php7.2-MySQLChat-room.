# H5 + PHP 7.4 + MySQL Chatroom

## Introduction

A lightweight real-time online chatroom system built with H5 frontend, PHP 7.4+ backend, and MySQL database. Implemented natively without any third-party IM frameworks, using pure native PHP + HTML + JS with polling for real-time chat. Features a complete admin panel, user system, IP banning, avatar upload, custom themes, Emoji support, image sending, and more. Supports private deployment and is compatible with virtual hosts, BT Panel, and other PHP environments.

### Overall Architecture

1. **Frontend Layer**: Native HTML5 + CSS3 + jQuery, no heavy frameworks like Vue/React. Lightweight loading, compatible with PC and mobile devices. Supports dark mode, custom bubble colors, custom chat backgrounds, message sound effects, desktop notifications, code block rendering, image preview, and a full Emoji expression panel.

2. **Backend Layer**: PHP 7.4 and above, using PDO for MySQL operations. Pure native development without any PHP framework. File-based single-page development with separated APIs for message fetching, sending, online users, user profiles, and admin operations. Uses Session for login authentication and CSRF protection for backend operations.

3. **Database Layer**: MySQL 5.7+/8.0, character set utf8mb4 (full support for emoji storage). Contains five data tables: users, chat messages, online users, IP blacklist, and user location. Foreign key associations ensure data integrity.

4. **File Storage Layer**: Local file storage. Avatars and chat images are uploaded to the `uploads` directory, system logs are stored in `logs`, and static resources are stored in `assets`.

### File Structure Description

```
./
├── admin/
│   ├── index.php
│   ├── ip_blacklist.php
│   ├── user_messages.php
│   └── users.php
├── api/
│   ├── check_status.php
│   ├── delete_message.php
│   ├── messages.php
│   ├── offline.php
│   ├── online.php
│   ├── send_message.php
│   ├── update_location.php
│   └── users.php
├── avatars/
│   └── default-avatar.png
├── js/
│   └── chat.js
├── ogg/
│   └── 1.ogg
├── chat.php
├── config.php
├── index.php
├── install.lock
├── install.php
├── login.php
├── logout.php
├── profile.php
└── register.php
```

## Installation Tutorial

1. Upload all project source code to the website root directory, set the website running directory to the source code root directory, and grant read/write permissions to the folder.

2. Access the domain via browser: `http://your-domain/install.php` to enter the one-click installation page.

3. Fill in database information: database host, database username, database password, custom database name (will be automatically created if it doesn't exist).

4. Set the admin username, login password, and display nickname.

5. Click [Start Installation], and the program will automatically complete: create database, generate 5 data tables, generate `config.php` configuration file, automatically create uploads/logs resource folders, write admin account, and generate installation lock `install.lock`.

6. After installation is complete, click to jump to the login page, and log in to the chatroom or admin panel with the admin account.

**Reinstallation Instructions**: If you need to reinstall, manually delete the `config.php` and `install.lock` files in the root directory, then revisit install.php.

### Environment Requirements

1. PHP ≥ 7.4 (supports 8.x)
2. MySQL 5.7 / 8.0
3. Required PHP extensions: `pdo`, `pdo_mysql`, `gd`, `json`
4. Server directory read/write permissions (777 permissions for uploads and logs folders)

### Installation Steps

1. Upload all project source code to the website root directory, set the website running directory to the source code root directory, and grant read/write permissions to the folder.

2. Access the domain via browser: `http://your-domain/install.php` to enter the one-click installation page.

3. Fill in database information: database host, database username, database password, custom database name (will be automatically created if it doesn't exist).

4. Set the admin username, login password, and display nickname.

5. Click [Start Installation], and the program will automatically complete: create database, generate 5 data tables, generate `config.php` configuration file, automatically create uploads/logs resource folders, write admin account, and generate installation lock `install.lock`.

6. After installation is complete, click to jump to the login page, and log in to the chatroom or admin panel with the admin account.

### Reinstallation Instructions

If you need to reinstall, manually delete the `config.php` and `install.lock` files in the root directory, then revisit install.php.

## Usage Instructions

### Regular User Features

1. **Registration & Login**: Supports account registration. IP blacklisted users will be directly returned a banned image when accessing registration/login pages; disabled accounts cannot log in.

2. **Chat Sending**: Supports plain text, multi-line text, code blocks, local image upload and sending, with a built-in fully categorized Emoji selector.

3. **Interface Settings**: Dark/light mode toggle, custom send/receive bubble colors, custom image/solid color chat backgrounds, message notification sound toggle, volume adjustment, new message desktop notifications, auto-scroll message toggle.

4. **Personal Profile**: Modify avatar, nickname, personal signature, and support changing login password.

5. **Online List**: Real-time view of currently online users, displaying user nickname/avatar/city, with admin badge distinction.

6. **Message Permissions**: Regular users can only delete messages sent by themselves.

### Admin Exclusive Features

1. **Chat Permissions**: Can delete all messages sent by any user.

2. **Admin Panel Entry**: Access the management panel from the chatroom sidebar.

3. **Dashboard**: Statistics on total users, active/banned users, IP blacklist count, today's messages, today's active users, and other data.

4. **User Management**: View all registered users, disable/unban accounts, permanently delete users, one-click ban user IP, customize user display location.

5. **Message Traceability**: Individually view all historical chat records of any user, support deleting one by one.

6. **IP Blacklist**: Manually add malicious IP bans, unban banned IPs. Banned IPs cannot access login/registration pages.

## Operation Notes

1. The project relies on frontend 3-second polling to fetch the latest messages, and refreshes the online user list every 15 seconds. No WebSocket required, can run stably on ordinary virtual hosts.

2. Single file upload limit for avatars and images is 10MB, supporting jpg/png/gif/webp formats.

3. Automatically obtains the user's IP corresponding city through the ip-api interface. Without internet environment, it will default to display "Unknown".

4. Database password and account are stored in plain text in config.php. Do not leak this file in production environments.

--- This chatroom is licensed under the MIT License ---
