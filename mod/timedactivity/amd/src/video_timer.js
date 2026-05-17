define(['jquery', 'core/ajax', 'core/str', 'core/notification'], function($, Ajax, Str, Notification) {
    var videoTimer = {
        config: {},
        player: null,
        isPlaying: false,
        lastKnownTime: 0,
        intervalId: null,
        popupShown: {},

        init: function(config) {
            this.config = config;
            this.lastKnownTime = config.savedPosition || 0;
            this.setupVideo();
            this.startTimer();
            this.updateDisplay();
        },

        setupVideo: function() {
            var self = this;
            var container = $('#video-player');
            if (this.config.videoSource === 'local' && this.config.videoFileUrl) {
                var video = $('<video id="main-video" controls playsinline style="width:100%;"><source src="' + this.config.videoFileUrl + '"></video>');
                container.html(video);
                this.player = video[0];
                this.player.currentTime = this.config.savedPosition;
                this.attachLocalEvents();
            } else if (this.config.videoSource === 'youtube' && this.config.youtubeUrl) {
                var videoId = this.getYoutubeId(this.config.youtubeUrl);
                var iframe = $('<iframe id="yt-player" width="100%" height="450" src="https://www.youtube.com/embed/' + videoId + '?enablejsapi=1" frameborder="0"></iframe>');
                container.html(iframe);
                this.initYoutubeApi();
            }
        },

        getYoutubeId: function(url) {
            var regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
            var match = url.match(regExp);
            return (match && match[2].length === 11) ? match[2] : null;
        },

        initYoutubeApi: function() {
            var self = this;
            if (typeof YT === 'undefined' || typeof YT.Player === 'undefined') {
                $.getScript('https://www.youtube.com/iframe_api');
                window.onYouTubeIframeAPIReady = function() { self.createYoutubePlayer(); };
            } else {
                this.createYoutubePlayer();
            }
        },

        createYoutubePlayer: function() {
            var self = this;
            this.player = new YT.Player('yt-player', {
                events: {
                    'onReady': function() {
                        if (self.config.savedPosition > 0) self.player.seekTo(self.config.savedPosition);
                        if (self.config.matchDuration) self.sendDuration(self.player.getDuration());
                    },
                    'onStateChange': function(e) {
                        if (e.data === YT.PlayerState.PLAYING) self.isPlaying = true;
                        else self.isPlaying = false;
                    }
                }
            });
        },

        attachLocalEvents: function() {
            var self = this;
            this.player.onplay = function() { self.isPlaying = true; };
            this.player.onpause = function() { self.isPlaying = false; self.savePosition(); };
            this.player.onseeking = function() {
                if (self.player.currentTime > self.lastKnownTime + 1) {
                    self.player.currentTime = self.lastKnownTime;
                }
            };
            this.player.ontimeupdate = function() {
                if (self.player.currentTime < self.lastKnownTime + 1) {
                    self.lastKnownTime = self.player.currentTime;
                }
                self.checkQuizzes(self.player.currentTime);
            };
            if (this.config.matchDuration) {
                this.player.onloadedmetadata = function() { self.sendDuration(self.player.duration); };
            }
        },

        startTimer: function() {
            var self = this;
            this.intervalId = setInterval(function() {
                if (self.isPlaying) {
                    self.config.current++;
                    self.updateDisplay();
                    if (self.config.current % 5 === 0) self.sendProgress(5);
                }
            }, 1000);
        },

        updateDisplay: function() {
            $('#timespent').text(this.formatTime(this.config.current));
            var remaining = Math.max(0, this.config.required - this.config.current);
            $('#timeremaining').text(this.formatTime(remaining));
            if (this.config.required > 0 && this.config.current >= this.config.required) {
                $('#completion-status').text('COMPLETED').css('color', 'green');
            }
        },

        formatTime: function(secs) {
            var h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60), s = secs % 60;
            return (h > 0 ? h + 'h ' : '') + (m > 0 || h > 0 ? m + 'm ' : '') + s + 's';
        },

        sendProgress: function(duration) {
            var self = this;
            $.post(this.config.ajaxUrl, {
                cmid: this.config.cmid,
                userid: this.config.userid,
                duration: duration,
                sesskey: this.config.sesskey
            }, function(data) {
                if (data.complete) $('#completion-status').text('COMPLETED').css('color', 'green');
            }, 'json');
        },

        savePosition: function() {
            var pos = this.player.currentTime || (this.player.getCurrentTime ? this.player.getCurrentTime() : 0);
            $.post(this.config.ajaxUrl.replace('track.php', 'save_position.php'), {
                cmid: this.config.cmid,
                userid: this.config.userid,
                position: pos,
                sesskey: this.config.sesskey
            });
        },

        sendDuration: function(d) {
            $.post(this.config.durationUrl, {
                cmid: this.config.cmid,
                duration: Math.floor(d),
                sesskey: this.config.sesskey
            });
        },

        checkQuizzes: function(time) {
            var self = this;
            this.config.quizQuestions.forEach(function(q) {
                if (!self.popupShown[q.id] && time >= q.timeposition) {
                    self.popupShown[q.id] = true;
                    self.showQuiz(q);
                }
            });
        },

        showQuiz: function(q) {
            if (this.player.pause) this.player.pause();
            if (this.player.pauseVideo) this.player.pauseVideo();
            
            var self = this;
            var index = this.config.quizQuestions.indexOf(q) + 1;
            var total = this.config.quizQuestions.length;
            
            alert("Question " + index + " of " + total + ": " + q.questiontext);
        }
    };
    return videoTimer;
});
