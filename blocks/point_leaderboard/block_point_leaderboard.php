<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

class block_point_leaderboard extends block_base {
    
    public function init() {
        $this->title = get_string('pluginname', 'block_point_leaderboard');
    }
    
    public function get_content() {
        global $COURSE, $OUTPUT, $USER, $CFG, $DB;
        
        if ($this->content !== null) {
            return $this->content;
        }
        
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';
        
        // Check if local plugin exists
        if (!file_exists($CFG->dirroot . '/local/point_badges/classes/manager.php')) {
            $this->content->text = '<div class="alert alert-warning">Point Badges plugin is not installed.</div>';
            return $this->content;
        }
        
        require_once($CFG->dirroot . '/local/point_badges/classes/manager.php');
        
        // Get configuration
        $config = $this->config;
        $global_mode = isset($config->global_mode) ? $config->global_mode : false;
        
        // Get user data for dashboard
        $user_level_info = \local_point_badges\manager::get_user_level_info($USER->id);
        $user_streak = \local_point_badges\manager::get_user_streak($USER->id);
        $current_streak = $user_streak ? $user_streak->current_streak : 0;
        $total_xp = $user_level_info['total_xp'];
        $current_level = $user_level_info['current_level'];
        $progress_percent = $user_level_info['progress_percent'];
        $xp_needed = $user_level_info['xp_needed_next_level'];
        
        // Level names and badges
        $level_names = ['', 'Beginner', 'Intermediate', 'Advanced', 'Expert'];
        $level_icons = ['', '🥉', '🥈', '🥇', '💎'];
        $level_colors = ['', '#cd7f32', '#c0c0c0', '#ffd700', '#e5e4e2'];
        $current_level_name = $level_names[$current_level];
        $current_level_icon = $level_icons[$current_level];
        
        // Get next level name
        $next_level_name = isset($level_names[$current_level + 1]) ? $level_names[$current_level + 1] : 'Max Level';
        
        // Get user's daily challenges
        $challenges = [];
        try {
            $challenges = \local_point_badges\manager::get_user_daily_challenges($USER->id);
        } catch (Exception $e) {
            // Silently fail if challenges not available
        }
        
        // Get user's rank
        $leaderboard = \local_point_badges\manager::get_leaderboard(null, 1000, 0);
        $user_rank = 'Unranked';
        foreach ($leaderboard as $lb_user) {
            if ($lb_user->id == $USER->id) {
                $user_rank = '#' . $lb_user->rank;
                break;
            }
        }
        
        // Build HTML with Tabs
        $html = '<div class="point-leaderboard-container">';
        
        // ========== TAB NAVIGATION ==========
        $html .= '<div class="pb-tabs-nav">
                    <button class="pb-tab-btn active" onclick="openPBTab(event, \'pb-stats\')">📊 Stats & Rank</button>
                    <button class="pb-tab-btn" onclick="openPBTab(event, \'pb-challenges\')">🎯 Challenges</button>
                    <button class="pb-tab-btn" onclick="openPBTab(event, \'pb-rewards\')">🎁 Rewards</button>
                  </div>';
        
        // ========== TAB 1: STATS & RANK ==========
        $html .= '<div id="pb-stats" class="pb-tab-content active">';
        
        // User Profile Section
        $user_picture = $OUTPUT->user_picture($USER, ['size' => 60, 'link' => false, 'class' => 'user-avatar-large']);
        
        $html .= '<div class="user-profile-section">
                    <div class="user-avatar-wrapper">' . $user_picture . '</div>
                    <div class="user-info-wrapper">
                        <h3 class="user-name">' . fullname($USER) . '</h3>
                        <div class="user-level-badge" style="background-color: ' . $level_colors[$current_level] . ';">
                            ' . $current_level_icon . ' ' . $current_level_name . '
                        </div>
                    </div>
                  </div>';
        
        // XP Progress Section
        $html .= '<div class="xp-progress-section">
                    <div class="xp-stats-row">
                        <div class="xp-total">
                            <span class="xp-label">Total XP</span>
                            <span class="xp-value">' . number_format($total_xp) . '</span>
                        </div>
                        <div class="xp-streak">
                            <span class="streak-label">🏆 Global Rank</span>
                            <span class="streak-value" style="color: #4caf50;">' . $user_rank . '</span>
                        </div>
                    </div>
                    <div class="progress-bar-wrapper">
                        <div class="progress-label">
                            <span>' . $current_level_name . '</span>
                            <span>' . $next_level_name . '</span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: ' . $progress_percent . '%;"></div>
                        </div>
                        <div class="progress-hint">
                            ' . $xp_needed . ' XP needed for ' . $next_level_name . '
                        </div>
                    </div>
                  </div>';
                  
        // Quick Stats
        $html .= '<div class="quick-stats-section">
                    <div class="stat-item">
                        <div class="stat-icon">🔥</div>
                        <div class="stat-info">
                            <div class="stat-value">' . $current_streak . '</div>
                            <div class="stat-label">Day Streak</div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">📈</div>
                        <div class="stat-info">
                            <div class="stat-value">' . $progress_percent . '%</div>
                            <div class="stat-label">To Next Level</div>
                        </div>
                    </div>
                  </div>';
                  
        $html .= '<div class="view-leaderboard-footer" style="padding-top: 10px;">
                    <a href="' . new \moodle_url('/local/point_badges/leaderboard.php') . '" class="view-leaderboard-btn">
                        🏆 View Full Leaderboard →
                    </a>
                  </div>';
        
        $html .= '</div>'; // End Tab 1
        
        // ========== TAB 2: CHALLENGES ==========
        $html .= '<div id="pb-challenges" class="pb-tab-content" style="display:none;">';
        if (!empty($challenges)) {
            $html .= '<div class="challenges-section" style="margin-top: 0; box-shadow: none;">
                        <h4 class="section-title">✨ Complete tasks to earn extra XP!</h4>
                        <div class="challenges-list">';
            
            foreach ($challenges as $challenge) {
                $completed_class = $challenge->completed ? 'completed' : '';
                $progress_text = $challenge->progress . '/' . $challenge->required_count;
                $progress_percent_challenge = min(100, round(($challenge->progress / $challenge->required_count) * 100));
                
                $html .= '<div class="challenge-item ' . $completed_class . '">
                            <div class="challenge-icon">🎯</div>
                            <div class="challenge-info">
                                <div class="challenge-name">' . s($challenge->name) . '</div>
                                <div class="challenge-desc">' . s($challenge->description) . '</div>
                                <div class="challenge-progress-wrapper">
                                    <div class="challenge-progress-bg">
                                        <div class="challenge-progress-fill" style="width: ' . $progress_percent_challenge . '%;"></div>
                                    </div>
                                    <span class="challenge-progress-text">' . $progress_text . '</span>
                                </div>
                            </div>
                            <div class="challenge-reward">+' . $challenge->xp_reward . ' XP</div>
                          </div>';
            }
            
            $html .= '</div></div>';
        } else {
            $html .= '<div class="empty-leaderboard">You have no active challenges today. Log in tomorrow!</div>';
        }
        
        $html .= '<div class="view-leaderboard-footer" style="padding-top: 10px;">
                    <a href="' . new \moodle_url('/local/point_badges/challenges.php') . '" class="view-leaderboard-btn" style="background: #ff9800;">
                        📋 View All Challenges →
                    </a>
                  </div>';
        $html .= '</div>'; // End Tab 2
        
        // ========== TAB 3: REWARDS ==========
        $html .= '<div id="pb-rewards" class="pb-tab-content" style="display:none; padding: 15px;">';
        $html .= '<div style="text-align: center; margin-bottom: 15px;">
                    <h4 style="margin:0; font-size: 1.1rem;">🎁 Reward Shop</h4>
                    <p style="font-size:0.8rem; color:#666; margin-top:5px;">Spend your XP on exclusive rewards!</p>
                  </div>';
                  
        $html .= '<div class="reward-mini-list">
                    <div class="reward-mini-item">
                        <span class="reward-icon">🏅</span> 
                        <span class="reward-name">Certificates</span>
                    </div>
                    <div class="reward-mini-item">
                        <span class="reward-icon">🎟️</span> 
                        <span class="reward-name">Discount Coupons</span>
                    </div>
                    <div class="reward-mini-item">
                        <span class="reward-icon">📝</span> 
                        <span class="reward-name">Extra Quiz Attempts</span>
                    </div>
                    <div class="reward-mini-item">
                        <span class="reward-icon">⭐</span> 
                        <span class="reward-name">Premium Content</span>
                    </div>
                  </div>';
                  
        $html .= '<div class="view-leaderboard-footer" style="padding-top: 20px;">
                    <a href="' . new \moodle_url('/local/point_badges/shop.php') . '" class="view-leaderboard-btn" style="background: #4caf50;">
                        🛍️ Visit Reward Shop →
                    </a>
                  </div>';
        $html .= '</div>'; // End Tab 3
        
        // ========== JAVASCRIPT FOR TABS ==========
        $html .= '<script>
            function openPBTab(evt, tabName) {
                var i, tabcontent, tablinks;
                tabcontent = document.getElementsByClassName("pb-tab-content");
                for (i = 0; i < tabcontent.length; i++) {
                    tabcontent[i].style.display = "none";
                    tabcontent[i].classList.remove("active");
                }
                tablinks = document.getElementsByClassName("pb-tab-btn");
                for (i = 0; i < tablinks.length; i++) {
                    tablinks[i].classList.remove("active");
                }
                document.getElementById(tabName).style.display = "block";
                document.getElementById(tabName).classList.add("active");
                evt.currentTarget.classList.add("active");
            }
        </script>';
        
        // ========== STYLES ==========
        $html .= '<style>
            .point-leaderboard-container {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: #f8f9fa;
                border-radius: 16px;
                padding: 0;
                overflow: hidden;
            }
            /* Tab Navigation */
            .pb-tabs-nav {
                display: flex;
                background: #fff;
                border-bottom: 2px solid #e0e0e0;
            }
            .pb-tab-btn {
                flex: 1;
                background: inherit;
                border: none;
                outline: none;
                cursor: pointer;
                padding: 12px 10px;
                font-size: 0.8rem;
                font-weight: 600;
                color: #666;
                transition: 0.3s;
                border-bottom: 3px solid transparent;
            }
            .pb-tab-btn:hover {
                background-color: #f1f1f1;
            }
            .pb-tab-btn.active {
                color: #667eea;
                border-bottom: 3px solid #667eea;
            }
            .pb-tab-content {
                animation: fadeIn 0.4s;
            }
            @keyframes fadeIn {
                from {opacity: 0;}
                to {opacity: 1;}
            }
            /* User Profile Section */
            .user-profile-section {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 20px;
                display: flex;
                align-items: center;
                gap: 15px;
                color: white;
            }
            .user-avatar-large {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                border: 3px solid white;
                object-fit: cover;
            }
            .user-info-wrapper {
                flex: 1;
            }
            .user-name {
                margin: 0 0 5px 0;
                font-size: 1.2rem;
                font-weight: 600;
            }
            .user-level-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 0.75rem;
                font-weight: 600;
                background: rgba(255,255,255,0.2);
                backdrop-filter: blur(5px);
            }
            /* XP Progress Section */
            .xp-progress-section {
                background: white;
                padding: 15px;
                margin: 10px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            .xp-stats-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 12px;
            }
            .xp-total, .xp-streak {
                display: flex;
                flex-direction: column;
            }
            .xp-label, .streak-label {
                font-size: 0.7rem;
                color: #888;
            }
            .xp-value, .streak-value {
                font-size: 1.3rem;
                font-weight: bold;
                color: #333;
            }
            .progress-bar-wrapper {
                margin-top: 10px;
            }
            .progress-label {
                display: flex;
                justify-content: space-between;
                font-size: 0.7rem;
                color: #666;
                margin-bottom: 5px;
            }
            .progress-bar-bg {
                background: #e0e0e0;
                border-radius: 10px;
                height: 8px;
                overflow: hidden;
            }
            .progress-bar-fill {
                background: linear-gradient(90deg, #667eea, #764ba2);
                height: 100%;
                border-radius: 10px;
                transition: width 0.5s ease;
            }
            .progress-hint {
                font-size: 0.65rem;
                color: #999;
                margin-top: 6px;
                text-align: right;
            }
            /* Challenges Section */
            .challenges-section {
                background: white;
                margin: 10px;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            .section-title {
                padding: 12px 15px;
                margin: 0;
                font-size: 0.9rem;
                background: #f8f9fa;
                border-bottom: 1px solid #e0e0e0;
                color: #333;
                text-align: center;
            }
            .challenges-list {
                padding: 5px 0;
            }
            .challenge-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 15px;
                border-bottom: 1px solid #f0f0f0;
                transition: background 0.2s ease;
            }
            .challenge-item:hover {
                background: #fafafa;
            }
            .challenge-item.completed {
                background: #e8f5e9;
                opacity: 0.85;
            }
            .challenge-icon {
                font-size: 1.8rem;
            }
            .challenge-info {
                flex: 1;
            }
            .challenge-name {
                font-weight: bold;
                font-size: 0.85rem;
                color: #333;
            }
            .challenge-desc {
                font-size: 0.7rem;
                color: #666;
                margin: 3px 0;
            }
            .challenge-progress-wrapper {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-top: 6px;
            }
            .challenge-progress-bg {
                flex: 1;
                background: #e0e0e0;
                border-radius: 10px;
                height: 6px;
                overflow: hidden;
            }
            .challenge-progress-fill {
                background: #4caf50;
                height: 100%;
                border-radius: 10px;
                transition: width 0.3s ease;
            }
            .challenge-progress-text {
                font-size: 0.65rem;
                color: #666;
                min-width: 35px;
                text-align: right;
            }
            .challenge-reward {
                font-size: 0.7rem;
                font-weight: bold;
                color: #4caf50;
                white-space: nowrap;
            }
            /* Rewards tab */
            .reward-mini-list {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .reward-mini-item {
                display: flex;
                align-items: center;
                background: white;
                padding: 12px 15px;
                border-radius: 8px;
                border-left: 4px solid #667eea;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            .reward-icon {
                font-size: 1.5rem;
                margin-right: 15px;
            }
            .reward-name {
                font-weight: 600;
                color: #333;
            }
            /* Quick Stats Section */
            .quick-stats-section {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                padding: 10px;
                background: white;
                margin: 10px;
                border-radius: 12px;
            }
            .stat-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px;
                background: #f8f9fa;
                border-radius: 10px;
            }
            .stat-icon {
                font-size: 1.5rem;
            }
            .stat-info {
                flex: 1;
            }
            .stat-value {
                font-size: 1rem;
                font-weight: bold;
                color: #333;
            }
            .stat-label {
                font-size: 0.65rem;
                color: #888;
            }
            /* View Leaderboard Button */
            .view-leaderboard-footer {
                padding: 12px 15px;
                text-align: center;
                background: transparent;
                margin: 10px;
                margin-top: 0;
            }
            .view-leaderboard-btn {
                display: inline-block;
                padding: 10px 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 25px;
                font-size: 0.8rem;
                font-weight: 500;
                transition: transform 0.2s ease, opacity 0.2s ease;
                width: 100%;
                box-sizing: border-box;
            }
            .view-leaderboard-btn:hover {
                transform: translateY(-2px);
                opacity: 0.9;
                color: white;
                text-decoration: none;
            }
            .empty-leaderboard {
                padding: 20px;
                text-align: center;
                color: #999;
                font-size: 0.85rem;
            }
        </style>';
        
        $this->content->text = $html;
        
        return $this->content;
    }
    
    public function instance_allow_multiple() {
        return true;
    }
    
    public function has_config() {
        return true;
    }
    
    public function instance_config_save($data, $nolongerused = false) {
        $config = new stdClass();
        $config->entries = isset($data->entries) ? (int)$data->entries : 5;
        $config->global_mode = isset($data->global_mode) ? (int)$data->global_mode : 0;
        parent::instance_config_save($config, $nolongerused);
    }
    
    public function specialization() {
        if (isset($this->config->title) && !empty($this->config->title)) {
            $this->title = $this->config->title;
        } else {
            $this->title = get_string('pluginname', 'block_point_leaderboard');
        }
    }
}