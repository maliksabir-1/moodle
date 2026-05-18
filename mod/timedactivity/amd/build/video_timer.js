define(['jquery', 'core/ajax', 'core/str', 'core/notification'], function($, Ajax, Str, Notification) {
    var videoTimer = {
        config: {},
        player: null,
        isPlaying: false,
        wasPlayingBeforeHide: false,
        lastKnownTime: 0,
        intervalId: null,
        popupShown: {},
        timeSpent: 0,

        init: function(config) {
            this.config = config;
            this.timeSpent = config.current || 0;
            this.lastKnownTime = config.savedPosition || 0;
            this.quizAnswered = config.quizAnswered || 0;
            this.totalQuizzes = config.totalQuizzes || 0;
            this.popupShown = {};
            
            var self = this;
            
            // 1. Mark already attempted/answered quizzes as shown
            if (this.config.answeredQuizIds) {
                this.config.answeredQuizIds.forEach(function(qid) {
                    self.popupShown[qid] = true;
                });
            }
            
            // 2. Mark quizzes that are behind the starting resume position as shown
            var startPos = config.savedPosition || 0;
            if (this.config.quizQuestions) {
                this.config.quizQuestions.forEach(function(q) {
                    if (q.timeposition <= startPos) {
                        self.popupShown[q.id] = true;
                    }
                });
            }

            this.setupVideo();
            this.startTimer();
            this.updateDisplay();
            this.setupVisibilityListener();
            this.updateQuizRequirementsDisplay();
        },

        setupVideo: function() {
            var self = this;
            var container = $('#video-player');
            if (!container.length) {
                console.error('Video container not found');
                return;
            }
            
            if (this.config.videoSource === 'local' && this.config.videoFileUrl) {
                var videoHtml = '<video id="main-video" controls playsinline style="width:100%;" preload="metadata">' +
                               '<source src="' + this.config.videoFileUrl + '" type="video/mp4">' +
                               'Your browser does not support the video tag.</video>';
                container.html(videoHtml);
                this.player = document.getElementById('main-video');
                if (this.player) {
                    this.player.currentTime = this.config.savedPosition;
                    this.attachLocalEvents();
                } else {
                    console.error('Video element not found');
                }
            } else if (this.config.videoSource === 'youtube' && this.config.youtubeUrl) {
                var videoId = this.getYoutubeId(this.config.youtubeUrl);
                if (videoId) {
                    var iframeHtml = '<div id="yt-player-container"><div id="yt-player"></div></div>';
                    container.html(iframeHtml);
                    this.initYoutubeApi(videoId);
                }
            }
        },

        getYoutubeId: function(url) {
            var regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
            var match = url.match(regExp);
            return (match && match[2].length === 11) ? match[2] : null;
        },

        initYoutubeApi: function(videoId) {
            var self = this;
            this.youtubeVideoId = videoId;
            
            if (typeof YT === 'undefined' || typeof YT.Player === 'undefined') {
                var tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                var firstScriptTag = document.getElementsByTagName('script')[0];
                firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
                
                window.onYouTubeIframeAPIReady = function() {
                    self.createYoutubePlayer();
                };
            } else {
                this.createYoutubePlayer();
            }
        },

        createYoutubePlayer: function() {
            var self = this;
            this.player = new YT.Player('yt-player', {
                height: '100%',
                width: '100%',
                videoId: this.youtubeVideoId,
                playerVars: {
                    'controls': 1,
                    'enablejsapi': 1,
                    'origin': window.location.origin
                },
                events: {
                    'onReady': function(event) {
                        if (self.config.savedPosition > 0) {
                            event.target.seekTo(self.config.savedPosition);
                        }
                        if (self.config.matchDuration) {
                            self.sendDuration(event.target.getDuration());
                        }
                        self.attachYoutubeEvents();
                    },
                    'onStateChange': function(event) {
                        if (event.data === YT.PlayerState.PLAYING) {
                            self.isPlaying = true;
                        } else if (event.data === YT.PlayerState.PAUSED || 
                                   event.data === YT.PlayerState.ENDED) {
                            self.isPlaying = false;
                            if (event.data === YT.PlayerState.PAUSED) {
                                self.savePosition();
                            }
                        }
                    }
                }
            });
        },

        attachYoutubeEvents: function() {
            var self = this;
            // Poll for time updates on YouTube player
            this.checkInterval = setInterval(function() {
                if (self.isPlaying && self.player && typeof self.player.getCurrentTime === 'function') {
                    var currentTime = self.player.getCurrentTime();
                    if (currentTime > self.lastKnownTime + 1) {
                        // Prevent seeking forward beyond saved position
                        self.player.seekTo(self.lastKnownTime);
                    } else {
                        self.lastKnownTime = currentTime;
                    }
                    self.checkQuizzes(currentTime);
                }
            }, 500);
        },

        attachLocalEvents: function() {
            var self = this;
            
            this.player.addEventListener('play', function() { 
                self.isPlaying = true; 
            });
            
            this.player.addEventListener('pause', function() { 
                self.isPlaying = false; 
                self.savePosition(); 
            });
            
            this.player.addEventListener('ended', function() {
                self.isPlaying = false;
                self.savePosition();
            });
            
            this.player.addEventListener('seeking', function() {
                if (Math.abs(self.player.currentTime - self.lastKnownTime) > 1) {
                    self.player.currentTime = self.lastKnownTime;
                }
            });
            
            this.player.addEventListener('timeupdate', function() {
                if (Math.abs(self.player.currentTime - self.lastKnownTime) <= 1) {
                    self.lastKnownTime = self.player.currentTime;
                }
                self.checkQuizzes(self.player.currentTime);
            });
            
            if (this.config.matchDuration) {
                this.player.addEventListener('loadedmetadata', function() { 
                    self.sendDuration(self.player.duration); 
                });
            }
        },

        startTimer: function() {
            var self = this;
            this.intervalId = setInterval(function() {
                if (self.isPlaying) {
                    self.timeSpent++;
                    self.updateDisplay();
                    
                    // Send progress and save current position in database every 1 second
                    self.sendProgress(1);
                    
                    // Check if required time is met
                    if (self.timeSpent >= self.config.required) {
                        $('#completion-status').text('COMPLETED').removeClass('incomplete').addClass('complete');
                    }
                }
            }, 1000);
        },

        updateDisplay: function() {
            $('#timespent').text(this.formatTime(this.timeSpent));
            var remaining = Math.max(0, this.config.required - this.timeSpent);
            $('#timeremaining').text(this.formatTime(remaining));
            if (this.config.required > 0 && this.timeSpent >= this.config.required) {
                $('#completion-status').text('COMPLETED').removeClass('incomplete').addClass('complete');
            } else {
                $('#completion-status').text('IN PROGRESS').removeClass('complete').addClass('incomplete');
            }

            // Live-update the Time Spent Requirement row
            if ($('#req-time').length) {
                if (this.config.required > 0 && this.timeSpent >= this.config.required) {
                    $('#req-time')
                        .html('✅&nbsp; Watch at least <strong>' + this.formatTime(this.config.required) + '</strong>')
                        .css({
                            'background': '#d4edda',
                            'color': '#155724'
                        })
                        .removeClass('req-unmet').addClass('req-met');
                } else {
                    $('#req-time')
                        .html('❌&nbsp; Watch at least <strong>' + this.formatTime(this.config.required) + '</strong>')
                        .css({
                            'background': '#f8d7da',
                            'color': '#721c24'
                        })
                        .removeClass('req-met').addClass('req-unmet');
                }
            }
        },

        updateQuizRequirementsDisplay: function() {
            if ($('#req-quizzes').length) {
                var total = this.totalQuizzes;
                var answered = Math.min(total, this.quizAnswered);
                
                if (answered >= total && total > 0) {
                    $('#req-quizzes')
                        .html('✅&nbsp; Answer all <strong>' + total + '</strong> quiz question' + (total > 1 ? 's' : '') + ' (' + answered + '/' + total + ' answered correctly)')
                        .css({
                            'background': '#d4edda',
                            'color': '#155724'
                        })
                        .removeClass('req-unmet').addClass('req-met');
                } else {
                    $('#req-quizzes')
                        .html('❌&nbsp; Answer all <strong>' + total + '</strong> quiz question' + (total > 1 ? 's' : '') + ' (' + answered + '/' + total + ' answered correctly)')
                        .css({
                            'background': '#f8d7da',
                            'color': '#721c24'
                        })
                        .removeClass('req-met').addClass('req-unmet');
                }
            }
        },

        updateGradeRequirementDisplay: function(grade, passed) {
            if ($('#req-grade').length) {
                var passingGrade = parseInt(this.config.passingGrade || 40, 10);
                var icon = passed ? '✅' : '❌';
                var color = passed ? '#155724' : '#721c24';
                var bg = passed ? '#d4edda' : '#f8d7da';
                var gradeLabel = ' (Your grade: ' + grade + '%)';
                
                $('#req-grade')
                    .html(icon + '&nbsp; Achieve a passing grade of <strong>' + passingGrade + '%</strong>' + gradeLabel)
                    .css({
                        'background': bg,
                        'color': color
                    });
            }
        },

        formatTime: function(secs) {
            var h = Math.floor(secs / 3600);
            var m = Math.floor((secs % 3600) / 60);
            var s = secs % 60;
            var parts = [];
            if (h > 0) parts.push(h + 'h');
            if (m > 0 || h > 0) parts.push(m + 'm');
            parts.push(s + 's');
            return parts.join(' ');
        },

        sendProgress: function(duration) {
            var self = this;
            var pos = 0;
            if (this.player) {
                if (this.player.currentTime !== undefined) {
                    pos = this.player.currentTime;
                } else if (this.player.getCurrentTime) {
                    pos = this.player.getCurrentTime();
                }
            }
            $.post(this.config.ajaxUrl, {
                cmid: this.config.cmid,
                userid: this.config.userid,
                visitid: this.config.visitId || 0,
                duration: duration,
                position: pos,
                sesskey: this.config.sesskey
            }, function(data) {
                // Reset Moodle frontend session timeout timer
                if (window.M && M.session_timeout && typeof M.session_timeout.reset === 'function') {
                    M.session_timeout.reset();
                }
                
                if (data.complete) {
                    $('#completion-status').text('COMPLETED').removeClass('incomplete').addClass('complete');
                    // Trigger Moodle completion check
                    if (typeof M !== 'undefined' && M.core_formchangechecker) {
                        M.core_formchangechecker.reset();
                    }
                }
            }, 'json').fail(function() {
                console.error('Failed to send progress');
            });
        },

        pauseVideo: function() {
            if (this.player) {
                if (typeof this.player.pause === 'function') {
                    try {
                        this.player.pause();
                    } catch (e) {
                        console.error('Error pausing local video:', e);
                    }
                } else if (this.player && typeof this.player.pauseVideo === 'function') {
                    try {
                        this.player.pauseVideo();
                    } catch (e) {
                        console.error('Error pausing YT video:', e);
                    }
                }
            }
        },

        playVideo: function() {
            if (this.player) {
                if (typeof this.player.play === 'function') {
                    try {
                        this.player.play();
                    } catch (e) {
                        console.error('Error playing local video:', e);
                    }
                } else if (this.player && typeof this.player.playVideo === 'function') {
                    try {
                        this.player.playVideo();
                    } catch (e) {
                        console.error('Error playing YT video:', e);
                    }
                }
            }
        },

        setupVisibilityListener: function() {
            var self = this;
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    self.wasPlayingBeforeHide = self.isPlaying;
                    if (self.isPlaying) {
                        console.log('Tab hidden, pausing active video.');
                        self.pauseVideo();
                    }
                } else {
                    if (self.wasPlayingBeforeHide) {
                        console.log('Tab visible, resuming active video.');
                        self.playVideo();
                    }
                }
            });
        },

        savePosition: function() {
            var pos = 0;
            if (this.player) {
                if (this.player.currentTime !== undefined) {
                    pos = this.player.currentTime;
                } else if (this.player.getCurrentTime) {
                    pos = this.player.getCurrentTime();
                }
            }
            
            $.post(this.config.savePositionUrl || this.config.ajaxUrl.replace('track.php', 'save_position.php'), {
                cmid: this.config.cmid,
                userid: this.config.userid,
                position: pos,
                sesskey: this.config.sesskey
            }).fail(function() {
                console.error('Failed to save position');
            });
        },

        sendDuration: function(d) {
            $.post(this.config.durationUrl, {
                cmid: this.config.cmid,
                duration: Math.floor(d),
                sesskey: this.config.sesskey
            }).fail(function() {
                console.error('Failed to send duration');
            });
        },

        checkQuizzes: function(time) {
            var self = this;
            if (!this.config.quizQuestions || !this.config.quizQuestions.length) {
                return;
            }
            
            this.config.quizQuestions.forEach(function(q) {
                // If user seeks back before the quiz time position, reset its shown flag (unless already answered)
                if (time < q.timeposition) {
                    if (!self.config.answeredQuizIds || self.config.answeredQuizIds.indexOf(q.id) === -1) {
                        self.popupShown[q.id] = false;
                    }
                }

                // Show quiz only if time reached and not shown/answered yet
                if (!self.popupShown[q.id] && time >= q.timeposition) {
                    self.popupShown[q.id] = true;
                    self.showQuiz(q);
                }
            });
        },

        showQuiz: function(q) {
            this.pauseVideo();
            
            var self = this;
            var options = q.options;
            if (typeof options === 'string') {
                try {
                    options = JSON.parse(options);
                } catch (e) {
                    console.error('Failed to parse options:', e);
                    options = options.replace(/[\[\]"]/g, '').split(',');
                }
            }
            
            var modalHtml = this.buildQuizModal(q, options);
            
            $('body').append(modalHtml);
            
            // Show custom modal
            var $modal = $('#quiz-modal-' + q.id);
            $modal.css('display', 'flex');

            var timerInterval = null;
            var limit = parseInt(this.config.timelimitperquestion, 10);
            if (!isNaN(limit) && limit > 0) {
                var timeLeft = limit;
                timerInterval = setInterval(function() {
                    timeLeft--;
                    $('#quiz-timer-' + q.id).text('Time remaining: ' + timeLeft + 's');
                    if (timeLeft <= 0) {
                        clearInterval(timerInterval);
                        handleAnswerSubmission(-1, true); // Time's up!
                    }
                }, 1000);
            }

            function handleAnswerSubmission(selectedAnswer, isTimeUp) {
                if (timerInterval) {
                    clearInterval(timerInterval);
                }
                
                // Disable all options and buttons
                $modal.find('input[name="answer"]').prop('disabled', true);
                $modal.find('.submit-answer').prop('disabled', true);
                $modal.find('.cancel-quiz').prop('disabled', true);
                
                var isCorrect = !isTimeUp && (selectedAnswer === q.correctanswer);
                
                if (isCorrect) {
                    self.quizAnswered++;
                    self.updateQuizRequirementsDisplay();
                }
                
                // Render feedback alert
                var alertClass = isCorrect ? 'alert-success' : 'alert-danger';
                var icon = isCorrect ? '✓' : '✗';
                var titleText = isCorrect ? 'Correct!' : (isTimeUp ? 'Time is up!' : 'Incorrect.');
                
                var feedbackHtml = '<div class="alert ' + alertClass + ' mt-3" style="padding: 15px; border-radius: 6px; font-size: 1.05em; line-height: 1.4; border: none; font-weight: 500;">' +
                                   '<div style="font-weight: bold; margin-bottom: 5px; font-size: 1.1em;">' + icon + ' ' + titleText + '</div>';
                
                if (!isCorrect) {
                    var correctText = options[q.correctanswer] || '';
                    feedbackHtml += '<div style="margin-bottom: 5px;"><strong>Correct answer:</strong> ' + correctText + '</div>';
                }
                
                if (q.explanation) {
                    feedbackHtml += '<div style="font-size: 0.95em; color: #495057; margin-top: 5px; padding-top: 5px; border-top: 1px solid rgba(0,0,0,0.08); font-style: italic;">' + q.explanation + '</div>';
                }
                
                feedbackHtml += '</div>';
                
                // Append feedback right in the custom modal body
                $modal.find('.timedactivity-custom-body').append(feedbackHtml);
                
                // Save answer in Moodle DB
                self.submitQuizAnswer(q, selectedAnswer, options);
                
                // Automatically close and resume after 3.5 seconds
                setTimeout(function() {
                    $modal.fadeOut(300, function() {
                        $(this).remove();
                        self.resumeVideo();
                    });
                }, 3500);
            }
            
            $modal.find('.submit-answer').off('click').on('click', function() {
                var selected = $modal.find('input[name="answer"]:checked').val();
                if (selected !== undefined) {
                    handleAnswerSubmission(parseInt(selected), false);
                } else {
                    alert('Please select an answer');
                }
            });

            // Make clicking on the custom option row select the radio button
            $modal.find('.radio').off('click').on('click', function(e) {
                if (e.target.tagName !== 'INPUT') {
                    var $radio = $(this).find('input[type="radio"]');
                    if (!$radio.prop('disabled')) {
                        $radio.prop('checked', true);
                    }
                }
            });

            $modal.find('.cancel-quiz, .timedactivity-custom-backdrop').off('click').on('click', function() {
                if (timerInterval) {
                    clearInterval(timerInterval);
                }
                $modal.fadeOut(300, function() {
                    $(this).remove();
                    self.resumeVideo();
                });
            });
        },
 
        buildQuizModal: function(q, options) {
            var radioButtons = '';
            for (var i = 0; i < options.length; i++) {
                radioButtons += '<div class="radio">' +
                               '<label>' +
                               '<input type="radio" name="answer" value="' + i + '"> ' + 
                               options[i] +
                               '</label>' +
                               '</div>';
            }
            
            var timerHtml = '';
            var limit = parseInt(this.config.timelimitperquestion, 10);
            if (!isNaN(limit) && limit > 0) {
                timerHtml = '<div class="quiz-timer alert alert-danger font-weight-bold text-center mb-3" id="quiz-timer-' + q.id + '">' +
                            'Time remaining: ' + limit + 's' +
                            '</div>';
            }
            
            return '<div class="timedactivity-custom-modal" id="quiz-modal-' + q.id + '">' +
                   '<div class="timedactivity-custom-backdrop"></div>' +
                   '<div class="timedactivity-custom-content">' +
                   '<div class="timedactivity-custom-header">' +
                   '<h5>Quiz Question</h5>' +
                   '</div>' +
                   '<div class="timedactivity-custom-body">' +
                   timerHtml +
                   '<p style="font-size: 1.1em; line-height: 1.5; color: #212529; font-weight:600;">' + q.questiontext + '</p>' +
                   '<div class="quiz-options">' +
                   radioButtons +
                   '</div>' +
                   '</div>' +
                   '<div class="timedactivity-custom-footer">' +
                   '<button type="button" class="btn btn-secondary cancel-quiz mr-2">Cancel</button>' +
                   '<button type="button" class="btn btn-primary submit-answer font-weight-bold px-4">Submit Answer</button>' +
                   '</div>' +
                   '</div>' +
                   '</div>';
        },

        submitQuizAnswer: function(q, answer, options) {
            var self = this;
            var isCorrect = (answer === q.correctanswer);
            
            Ajax.call([{
                methodname: 'mod_timedactivity_save_quiz_answer',
                args: {
                    quizid: q.id,
                    answer: answer,
                    iscorrect: isCorrect ? 1 : 0
                },
                done: function(response) {
                    if (response.success) {
                        console.log('Answer saved successfully.');
                        if (response.grade !== undefined && response.grade !== null) {
                            self.updateGradeRequirementDisplay(response.grade, response.passed);
                        }
                    }
                },
                fail: function(error) {
                    console.error('Failed to save answer:', error);
                }
            }]);
        },

        resumeVideo: function() {
            if (this.player) {
                if (typeof this.player.play === 'function') {
                    try {
                        this.player.play();
                    } catch (e) {
                        console.error('Error playing local video:', e);
                    }
                }
                if (typeof this.player.playVideo === 'function') {
                    try {
                        this.player.playVideo();
                    } catch (e) {
                        console.error('Error playing YT video:', e);
                    }
                }
            }
        },
    };
    
    return videoTimer;
});