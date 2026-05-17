document.addEventListener('DOMContentLoaded', function() {
    const submitBtn = document.getElementById('blogpost_submit');
    const messageDiv = document.getElementById('blogpost-message');

    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const heading = document.getElementById('blog_heading').value;
            const text = document.getElementById('blog_text').value;
            const tags = document.getElementById('blog_tags').value;
            const userid = document.getElementById('blog_userid').value;
            const username = document.getElementById('blog_username').value;
            
            // Get sesskey from multiple possible sources
            let sesskey = null;
            
            // Try to get from M.cfg
            if (window.M && window.M.cfg && window.M.cfg.sesskey) {
                sesskey = window.M.cfg.sesskey;
            }
            
            // Try to get from the page
            if (!sesskey) {
                const sesskeyElement = document.querySelector('input[name="sesskey"]');
                if (sesskeyElement) {
                    sesskey = sesskeyElement.value;
                }
            }
            
            // Try to get from the URL
            if (!sesskey) {
                const urlParams = new URLSearchParams(window.location.search);
                sesskey = urlParams.get('sesskey');
            }

            if (!heading || !text) {
                showMessage('Please fill in all fields', 'alert-danger');
                return;
            }
            
            if (!sesskey) {
                showMessage('Please refresh the page and try again', 'alert-danger');
                return;
            }

            // Disable button
            submitBtn.disabled = true;
            submitBtn.textContent = 'Posting...';

            // Create form data
            const formData = new FormData();
            formData.append('userid', userid);
            formData.append('username', username);
            formData.append('blog_heading', heading);
            formData.append('blog_text', text);
            formData.append('tags', tags);
            formData.append('sesskey', sesskey);

            // Send request
            fetch('/moodle/blocks/blogpost/ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'include',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data);
                
                if (data.status === 'success') {
                    showMessage(data.message, 'alert-success');
                    document.getElementById('blog_heading').value = '';
                    document.getElementById('blog_text').value = '';
                    document.getElementById('blog_tags').value = '';
                    
                    // Reload after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showMessage(data.message, 'alert-danger');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Post Blog';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred: ' + error.message, 'alert-danger');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Post Blog';
            });
        });
    }

    // Tag Filtering Logic
    const filterBadges = document.querySelectorAll('.tag-filter-badge');
    const blogCards = document.querySelectorAll('.blog-card');
    
    if (filterBadges.length > 0) {
        filterBadges.forEach(badge => {
            badge.addEventListener('click', function(e) {
                e.preventDefault();
                const selectedTag = this.getAttribute('data-tag').toLowerCase();
                
                // Update active classes
                filterBadges.forEach(b => {
                    b.classList.remove('active', 'badge-primary');
                    b.classList.add('badge-light');
                });
                this.classList.add('active', 'badge-primary');
                this.classList.remove('badge-light');
                
                // Filter cards
                blogCards.forEach(card => {
                    if (selectedTag === 'all') {
                        card.style.display = 'block';
                    } else {
                        const cardTagsStr = card.getAttribute('data-tags') || '';
                        const cardTags = cardTagsStr.split(',').map(t => t.trim());
                        if (cardTags.includes(selectedTag)) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    }
                });
                
                // Manage empty state message for each list (Admin and User)
                ['admin', 'user'].forEach(section => {
                    const sectionList = document.getElementById(`blogpost-list-${section}`);
                    if (sectionList) {
                        // Check cards that are not hidden
                        const visibleCards = Array.from(sectionList.querySelectorAll('.blog-card')).filter(card => card.style.display !== 'none');
                        let emptyMsg = sectionList.querySelector('.no-tag-posts-msg');
                        
                        if (visibleCards.length === 0) {
                            if (!emptyMsg) {
                                emptyMsg = document.createElement('p');
                                emptyMsg.className = 'text-muted small no-tag-posts-msg';
                                emptyMsg.textContent = 'No posts found for this tag.';
                                sectionList.appendChild(emptyMsg);
                            } else {
                                emptyMsg.style.display = 'block';
                            }
                        } else {
                            if (emptyMsg) {
                                emptyMsg.style.display = 'none';
                            }
                        }
                    }
                });
            });
        });

        // Click a tag on the card to trigger the filter badge!
        const cardTags = document.querySelectorAll('[data-tag-click]');
        cardTags.forEach(tagElement => {
            tagElement.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const tagValue = this.getAttribute('data-tag-click');
                const filterBadge = document.querySelector(`.tag-filter-badge[data-tag="${tagValue}"]`);
                if (filterBadge) {
                    filterBadge.click();
                }
            });
        });
    }

    function showMessage(msg, className) {
        if (!messageDiv) return;
        messageDiv.textContent = msg;
        messageDiv.className = 'alert ' + className;
        messageDiv.style.display = 'block';
        
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
});