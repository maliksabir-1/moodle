// /local/point_badges/amd/src/restrictions.js
define(['jquery', 'core/str'], function($, str) {
    return {
        init: function() {
            // Find and hide restricted activities if needed
            $('.activity').each(function() {
                var $activity = $(this);
                // Check for custom data attributes or restricted class
                if ($activity.find('.availabilityinfo').text().indexOf('premium') !== -1 ||
                    $activity.find('.availabilityinfo').text().indexOf('VIP') !== -1) {
                    $activity.addClass('local_point_badges_restricted');
                }
            });
            
            // Also check for any activity with availability restriction
            $('.availabilityinfo').each(function() {
                var $info = $(this);
                if ($info.text().indexOf('premium') !== -1 || $info.text().indexOf('VIP') !== -1) {
                    $info.closest('.activity').addClass('local_point_badges_restricted');
                }
            });
        }
    };
});