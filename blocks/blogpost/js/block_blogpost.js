document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('blogpost_submit');
    const messageDiv = document.getElementById('blogpost-message');

    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const heading = document.getElementById('blog_heading').value;
            const text = document.getElementById('blog_text').value;
            const userid = document.getElementById('blog_userid').value;
            const username = document.getElementById('blog_username').value;
            const sesskey = M.cfg.sesskey;

            if (!heading || !text) {
                showMessage('Please fill in all fields', 'alert-danger');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerText = 'Posting...';

            const formData = new FormData();
            formData.append('userid', userid);
            formData.append('username', username);
            formData.append('blog_heading', heading);
            formData.append('blog_text', text);
            formData.append('sesskey', sesskey);

            fetch(M.cfg.wwwroot + '/blocks/blogpost/ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showMessage(data.message, 'alert-success');
                    document.getElementById('blog_heading').value = '';
                    document.getElementById('blog_text').value = '';

                    // Create and prepend new card for immediate feedback
                    const listId = data.is_admin ? 'blogpost-list-admin' : 'blogpost-list-user';
                    const list = document.getElementById(listId);
                    if (list) {
                        // Remove "No posts yet" message if it exists
                        const noPostsMsg = list.querySelector('.text-muted');
                        if (noPostsMsg) {
                            noPostsMsg.remove();
                        }

                        const newCard = document.createElement('div');
                        newCard.className = 'blog-card card mb-2';
                        newCard.innerHTML = `
                            <div class="card-body p-3">
                                <h6 class="card-title mb-1">${escapeHtml(data.heading)}</h6>
                                <p class="card-subtitle mb-2 text-muted small">
                                    <i class="fa fa-user"></i> ${escapeHtml(data.author_name)} | <i class="fa fa-clock-o"></i> ${data.time_formatted}
                                </p>
                                <p class="card-text">${data.text}</p>
                            </div>
                        `;
                        list.insertBefore(newCard, list.firstChild);
                    }

                    // Auto reload the page after a brief delay to refresh the state
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage(data.message, 'alert-danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An unexpected error occurred.', 'alert-danger');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Post Blog';
            });
        });
    }

    function showMessage(msg, className) {
        messageDiv.innerText = msg;
        messageDiv.className = 'alert ' + className;
        messageDiv.style.display = 'block';
        
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
