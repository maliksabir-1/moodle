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
            const wwwroot = (window.M && window.M.cfg && window.M.cfg.wwwroot) ? window.M.cfg.wwwroot : '/moodle';
            const ajaxUrl = `${wwwroot}/blocks/blogpost/ajax.php`;
            
            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'include',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.text())
            .then(text => {
                // Sanitize response: Find the first '{'
                const jsonStart = text.indexOf('{');
                if (jsonStart === -1) {
                    throw new Error('Invalid server response');
                }
                const data = JSON.parse(text.substring(jsonStart));
                console.log('Response:', data);
                
                if (data.status === 'success') {
                    showMessage(data.message, 'alert-success');
                    document.getElementById('blog_heading').value = '';
                    document.getElementById('blog_text').value = '';
                    document.getElementById('blog_tags').value = '';
                    
                    // Dynamically insert the new post at the top of the user list
                    const listId = data.is_admin ? 'blogpost-list-admin' : 'blogpost-list-user';
                    const list = document.getElementById(listId);
                    if (list && data.new_post_html) {
                        const temp = document.createElement('div');
                        temp.innerHTML = data.new_post_html;
                        const newElement = temp.firstElementChild;
                        list.insertBefore(newElement, list.firstChild);
                        
                        // Scroll to the new post
                        newElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Post Blog';
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

    // Unified Event Delegation for Click Actions
    document.addEventListener('click', function(e) {
        // Toggle Nested Reply
        if (e.target.classList.contains('show-reply-input')) {
            e.preventDefault();
            const replyid = e.target.getAttribute('data-replyid');
            const form = document.getElementById(`reply-form-${replyid}`);
            const input = document.getElementById(`reply-input-${replyid}`);
            if (form) {
                form.style.display = (form.style.display === 'none') ? 'block' : 'none';
                if (form.style.display === 'block' && input) {
                    input.focus();
                }
            }
        }
        
        // Cancel Nested Reply
        if (e.target.classList.contains('cancel-reply')) {
            e.preventDefault();
            const replyid = e.target.getAttribute('data-replyid');
            const form = document.getElementById(`reply-form-${replyid}`);
            if (form) form.style.display = 'none';
        }

        // Toggle Main Reply
        if (e.target.classList.contains('show-main-reply')) {
            e.preventDefault();
            const postid = e.target.getAttribute('data-postid');
            const form = document.getElementById(`main-reply-form-${postid}`);
            if (form) {
                form.style.display = (form.style.display === 'none') ? 'block' : 'none';
                if (form.style.display === 'block') {
                    form.querySelector('input').focus();
                }
            }
        }

        if (e.target.classList.contains('toggle-replies')) {
            e.preventDefault();
            const targetId = e.target.getAttribute('data-target');
            const wrapper = document.getElementById(targetId);
            if (wrapper) {
                const isHidden = (wrapper.style.display === 'none');
                wrapper.style.display = isHidden ? 'block' : 'none';
                
                // Update text
                const count = e.target.textContent.match(/\d+/);
                if (isHidden) {
                    e.target.textContent = 'Hide replies';
                } else if (count) {
                    e.target.textContent = `View ${count[0]} replies`;
                } else {
                    e.target.textContent = 'View replies';
                }
            }
        }

        // Cancel Main Reply
        if (e.target.classList.contains('cancel-main-reply')) {
            e.preventDefault();
            const postid = e.target.getAttribute('data-postid');
            const form = document.getElementById(`main-reply-form-${postid}`);
            const linkDiv = document.querySelector(`.show-main-reply[data-postid="${postid}"]`)?.parentElement;
            if (form) {
                form.style.display = 'none';
                if (linkDiv) linkDiv.style.display = 'block';
            }
        }
    });

    // Submitting Reply (Delegated)
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('reply-submit')) {
            e.preventDefault();
            const btn = e.target;
            const postid = btn.getAttribute('data-postid');
            const parentid = btn.getAttribute('data-parentid') || 0;
            
            let inputField;
            if (parentid == 0) {
                // Find main reply input for this SPECIFIC post
                inputField = document.querySelector(`#main-reply-form-${postid} .reply-input`);
            } else {
                // Find nested reply input for this SPECIFIC parent
                inputField = document.getElementById(`reply-input-${parentid}`);
            }
            
            const replyText = inputField ? inputField.value.trim() : '';

            if (!replyText) {
                showMessage('Please enter a reply', 'alert-danger');
                return;
            }

            const wwwroot = (window.M && window.M.cfg && window.M.cfg.wwwroot) ? window.M.cfg.wwwroot : '/moodle';
            const sesskey = (window.M && window.M.cfg && window.M.cfg.sesskey) ? window.M.cfg.sesskey : document.querySelector('input[name="sesskey"]')?.value;

            if (!sesskey) {
                showMessage('Session key not found', 'alert-danger');
                return;
            }

            btn.disabled = true;
            btn.originalText = btn.textContent;
            btn.textContent = 'Replying...';

            const formData = new FormData();
            formData.append('postid', postid);
            formData.append('parentid', parentid);
            formData.append('reply_text', replyText);
            formData.append('sesskey', sesskey);

            fetch(`${wwwroot}/blocks/blogpost/ajax_reply.php`, {
                method: 'POST',
                body: formData,
                credentials: 'include',
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.text())
            .then(text => {
                const jsonStart = text.indexOf('{');
                if (jsonStart === -1) throw new Error('Invalid response');
                const data = JSON.parse(text.substring(jsonStart));

                if (data.status === 'success') {
                    showMessage('Reply posted successfully', 'alert-success');
                    if (inputField) inputField.value = '';
                    
                    // Dynamically insert the new reply
                    if (data.new_reply_html) {
                        const postid = data.postid;
                        const parentid = data.parentid;
                        
                        let targetContainer;
                        if (parentid == 0) {
                            // Append to the main replies section of the post
                            const postCard = document.querySelector(`.blog-card [data-postid="${postid}"]`).closest('.blog-card');
                            targetContainer = postCard.querySelector('.replies-section');
                            // Find the main reply form and insert BEFORE it
                            const mainForm = document.getElementById(`main-reply-form-${postid}`);
                            const temp = document.createElement('div');
                            temp.innerHTML = data.new_reply_html;
                            targetContainer.insertBefore(temp.firstElementChild, mainForm.closest('div'));
                        } else {
                            // This is a reply to another reply
                            // Find or create the "replies-wrapper" for the parent
                            let wrapper = document.getElementById(`replies-wrapper-${parentid}`);
                            if (!wrapper) {
                                // Find the parent reply item footer to insert after
                                const parentReplyItem = document.getElementById(`reply-form-${parentid}`).closest('.reply-item');
                                wrapper = document.createElement('div');
                                wrapper.id = `replies-wrapper-${parentid}`;
                                wrapper.className = 'replies-wrapper';
                                wrapper.style.display = 'block';
                                wrapper.style.transition = 'all 0.3s ease';
                                parentReplyItem.appendChild(wrapper);
                            }
                            
                            const temp = document.createElement('div');
                            temp.innerHTML = data.new_reply_html;
                            wrapper.style.display = 'block'; 
                            wrapper.appendChild(temp.firstElementChild);
                        }
                    }
                    
                    btn.disabled = false;
                    btn.textContent = btn.originalText;
                    
                    // Close the form
                    const form = btn.closest('.reply-form, .nested-reply-form');
                    if (form) form.style.display = 'none';
                    
                } else {
                    showMessage(data.message, 'alert-danger');
                    btn.disabled = false;
                    btn.textContent = btn.originalText;
                }
            })
            .catch(err => {
                showMessage('Error: ' + err.message, 'alert-danger');
                btn.disabled = false;
                btn.textContent = 'Reply';
            });
        }
    });

    // Mention Autocomplete logic
    let currentDropdown = null;
    let currentInput = null;
    let searchAbortController = null;
    function searchUsers(query, input) {
        if (searchAbortController) searchAbortController.abort();
        searchAbortController = new AbortController();
        
        let wwwroot = '';
        if (window.M && window.M.cfg && window.M.cfg.wwwroot) {
            wwwroot = window.M.cfg.wwwroot;
        } else {
            // Fallback for dev environments
            wwwroot = window.location.origin + (window.location.pathname.startsWith('/moodle') ? '/moodle' : '');
        }
        
        const url = `${wwwroot}/blocks/blogpost/ajax_user_search.php?q=${encodeURIComponent(query)}`;
        
        fetch(url, { signal: searchAbortController.signal })
            .then(res => res.json())
            .then(users => {
                // Remove existing dropdown if any
                if (currentDropdown) {
                    currentDropdown.remove();
                    currentDropdown = null;
                }

                // If no matching users, don't show anything
                if (!users || users.length === 0) return;
                
                const dropdown = document.createElement('div');
                dropdown.className = 'mention-dropdown';
                
                // Position based on input and scroll
                const rect = input.getBoundingClientRect();
                dropdown.style.left = `${rect.left + window.scrollX}px`;
                dropdown.style.top = `${rect.bottom + window.scrollY}px`;
                
                users.forEach(user => {
                    const item = document.createElement('div');
                    item.className = 'mention-item';
                    item.innerHTML = `<strong>${user.fullname}</strong> <span class="username">@${user.username}</span>`;
                    
                    item.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        const text = input.value;
                        const cursor = input.selectionStart;
                        const lastAt = text.substring(0, cursor).lastIndexOf('@');
                        
                        if (lastAt !== -1) {
                            const newText = text.substring(0, lastAt) + '@' + user.username + ' ' + text.substring(cursor);
                            input.value = newText;
                            input.setSelectionRange(lastAt + user.username.length + 2, lastAt + user.username.length + 2);
                        }
                        
                        dropdown.remove();
                        currentDropdown = null;
                        input.focus();
                    });
                    dropdown.appendChild(item);
                });
                
                document.body.appendChild(dropdown);
                currentDropdown = dropdown;
            })
            .catch(err => {
                if (err.name === 'AbortError') return;
                console.error('Mention Search Error:', err);
                if (currentDropdown) {
                    currentDropdown.remove();
                    currentDropdown = null;
                }
            });
    }

    const handleInputForMentions = function(e) {
        const text = this.value;
        const cursor = this.selectionStart;
        const textBeforeCursor = text.substring(0, cursor);
        
        // Regex: find @ followed by any word characters at the end of the text-before-cursor
        // Must be at start of string or preceded by a space/newline
        const mentionMatch = textBeforeCursor.match(/(?:^|\s)@([a-zA-Z0-9_.-]*)$/);
        
        if (mentionMatch) {
            const query = mentionMatch[1]; // The part after @
            searchUsers(query, this);
        } else if (currentDropdown) {
            currentDropdown.remove();
            currentDropdown = null;
        }
    };

    // Attach to initial inputs and use delegation for dynamically added ones
    document.addEventListener('input', function(e) {
        if (e.target.id === 'blog_text' || e.target.id === 'blog_tags' || e.target.classList.contains('reply-input')) {
            handleInputForMentions.call(e.target, e);
        }
    });

    const textarea = document.getElementById('blog_text');
    if (textarea) textarea.addEventListener('input', handleInputForMentions);

    // Close dropdown on click outside
    document.addEventListener('mousedown', (e) => {
        if (currentDropdown && !currentDropdown.contains(e.target)) {
            currentDropdown.remove();
            currentDropdown = null;
        }
    });

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