$(document).ready(function() {
    let lastMessageId = 0;
    let isScrolledToBottom = true;

    // 滚动检测
    $('#chat-messages').on('scroll', function() {
        const el = $(this)[0];
        isScrolledToBottom = el.scrollHeight - el.scrollTop <= el.clientHeight + 5;
    });

    // 获取消息
    function fetchMessages() {
        $.get('api/messages.php', { last_id: lastMessageId })
            .done(function(res) {
                if (!res.messages || res.messages.length === 0) return;
                res.messages.forEach(msg => {
                    appendMessage(msg);
                    lastMessageId = Math.max(lastMessageId, msg.id);
                });
                if (isScrolledToBottom) scrollToBottom();
            })
            .fail(err => console.error('获取消息失败', err));
    }

    // 插入单条消息
    function appendMessage(msg) {
        const isSelf = msg.user_id == $('#current-user-id').val();
        const adminBadge = msg.is_admin == 1 
            ? '<span class="admin-badge"><i class="fas fa-shield-alt"></i>管理员</span>' 
            : '';
        const location = msg.location ? `(${msg.location})` : '';
        const time = new Date(msg.created_at).toLocaleString();

        const html = `
        <div class="message ${isSelf ? 'message-self' : 'message-other'}" data-message-id="${msg.id}">
            <img src="${msg.avatar || 'assets/default-avatar.png'}" class="avatar" data-user-id="${msg.user_id}">
            <div class="message-content">
                <span class="username" data-user-id="${msg.user_id}">
                    ${msg.nickname || msg.username} ${adminBadge}
                    <small class="text-muted location-text">${location}</small>
                </span>
                <div class="bubble">${formatMsg(msg.content)}</div>
                <div class="time small text-muted">${time}</div>
            </div>
        </div>`;

        $(html).hide().appendTo('#chat-messages').fadeIn(200);
    }

    // 消息格式化：链接、换行、转义
    function formatMsg(content) {
        let txt = $('<div>').text(content).html();
        txt = txt.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank">$1</a>');
        txt = txt.replace(/\n/g, '<br>');
        return txt;
    }

    // 发送消息
    $('#message-form').on('submit', function(e) {
        e.preventDefault();
        const content = $('#message-input').val().trim();
        if (!content) return;

        $.post('api/messages.php', { content })
            .done(res => {
                if (res.success) {
                    $('#message-input').val('');
                    fetchMessages();
                    scrollToBottom();
                } else {
                    alert(res.error || '发送失败');
                }
            })
            .fail(err => {
                console.error(err);
                alert('发送失败');
            });
    });

    // 更新在线用户
    function updateOnlineUsers() {
        $.get('api/online.php')
            .done(res => {
                if (!res.success || !res.users) return;
                const list = res.users.map(user => {
                    const adminBadge = user.is_admin == 1 
                        ? '<span class="admin-badge ms-2"><i class="fas fa-shield-alt"></i>管理员</span>' 
                        : '';
                    const loc = user.location ? `(${user.location})` : '';
                    return `
                    <a href="#" class="list-group-item list-group-item-action d-flex align-items-center" data-user-id="${user.id}">
                        <img src="${user.avatar || 'assets/default-avatar.png'}" class="rounded-circle me-2" width="32" height="32">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center">
                                <span class="fw-bold">${user.nickname || user.username}</span>
                                ${adminBadge}
                                <small class="text-muted ms-2">${loc}</small>
                            </div>
                        </div>
                    </a>`;
                }).join('');
                $('#online-users').html(list);
                $('.online-count').text(res.total || 0);
            })
            .fail(err => console.error('获取在线用户失败', err));
    }

    // 打开用户资料弹窗
    $(document).on('click', '.username, .avatar, .list-group-item', function(e) {
        e.preventDefault();
        const uid = $(this).data('user-id');
        $.get('api/users.php', { user_id: uid })
            .done(res => {
                if (!res.user) return;
                const u = res.user;
                const html = `
                <div class="text-center p-3">
                    <img src="${u.avatar || 'assets/default-avatar.png'}" class="rounded-circle mb-3" width="100" height="100">
                    <h5>${u.nickname || u.username}</h5>
                    <p class="text-muted">${u.signature || '这个人很懒，什么都没写~'}</p>
                    <p>注册时间：${new Date(u.created_at).toLocaleDateString()}</p>
                </div>`;
                $('.modal-body').html(html);
                $('#userModal').modal('show');
            });
    });

    // 表情面板
    $('.emoji-picker-button').on('click', e => {
        e.stopPropagation();
        $('.emoji-panel').toggle();
    });

    $(document).on('click', e => {
        if (!$(e.target).closest('.emoji-picker-button, .emoji-panel').length) {
            $('.emoji-panel').hide();
        }
    });

    $(document).on('click', '.emoji-item', e => {
        e.preventDefault();
        const emoji = $(this).text();
        const ipt = $('#message-input');
        const pos = ipt[0].selectionStart;
        const val = ipt.val();
        ipt.val(val.slice(0, pos) + emoji + val.slice(pos));
        ipt.focus();
        ipt[0].setSelectionRange(pos + emoji.length, pos + emoji.length);
        $('.emoji-panel').hide();
    });

    // 滚动到底部
    function scrollToBottom() {
        const $el = $('#chat-messages');
        $el.scrollTop($el[0].scrollHeight);
    }

    // 回车发送
    $('#message-input').on('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            $('#message-form').submit();
        }
    });

    // 心跳
    setInterval(() => $.post('api/users.php', { action: 'heartbeat' }), 60000);

    // 初始化
    fetchMessages();
    updateOnlineUsers();
    scrollToBottom();
    setInterval(fetchMessages, 3000);
    setInterval(updateOnlineUsers, 15000);
    $.get('api/update_location.php');
});
