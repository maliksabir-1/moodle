<?php
defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Timed Activity';
$string['modulenameplural'] = 'Timed Activities';
$string['modulename_help'] = 'Use the Timed Activity module to require students spend a certain amount of time on the activity page before it is marked complete.';
$string['pluginname'] = 'Timed Activity';
$string['pluginadministration'] = 'Timed Activity administration';

$string['videosettings'] = 'Video settings';
$string['videosource'] = 'Video source';
$string['localvideo'] = 'Local video file';
$string['youtube'] = 'YouTube URL';
$string['videofile'] = 'Video file (MP4, WebM, OGV)';
$string['youtubeurl'] = 'YouTube URL';
$string['matchduration'] = 'Match required time to video duration';
$string['matchduration_help'] = 'When enabled, the required time is automatically set to the video duration.';
$string['matchduration_auto'] = 'Auto (video length)';

$string['timersettings'] = 'Timer Settings';
$string['timerequired'] = 'Required time';
$string['timerequired_help'] = 'Set the amount of time a student must spend on this activity page to mark it complete.';
$string['completiontime'] = 'Completion date/time';
$string['completiontime_help'] = 'Set a specific date and time when this activity will be automatically marked as complete.';

$string['quizpopups'] = 'Quiz popups';
$string['quizdata'] = 'Quiz questions (JSON format)';
$string['quizdata_help'] = 'Enter an array of questions: [{"time":30,"text":"Question?","options":["A","B"],"correct":0,"explanation":"..."}]';

$string['gradesettings'] = 'Grade settings';
$string['grademethod'] = 'Grade method';
$string['grademethod_none'] = 'No grade';
$string['grademethod_quiz'] = 'Quiz score only';
$string['grademethod_time'] = 'Time completion only';
$string['grademethod_both'] = 'Quiz + Time completion';
$string['passinggrade'] = 'Passing grade (%)';
$string['requiretimeforgrade'] = 'Require time completion for full grade';

$string['quizoptions'] = 'Quiz options';
$string['retakesallowed'] = 'Allow quiz retakes';
$string['randomizequestions'] = 'Randomize questions';
$string['timelimitperquestion'] = 'Time limit per question (seconds)';
$string['timelimitperquestion_help'] = 'Set to 0 for no time limit.';

$string['certificatesettings'] = 'Certificate settings';
$string['enablecertificate'] = 'Enable certificate on passing';
$string['downloadcertificate'] = 'Download Certificate';

$string['completionmessage_checkbox'] = 'Student must spend time on this activity';
$string['completionpass'] = 'Student must receive a passing grade';
$string['completionallquizzes'] = 'Student must answer all quiz popups';
$string['completionrequiretime'] = 'Require time spent';
$string['completionrequiretime_desc'] = 'Student must spend at least {$a} on this activity';
$string['completionpass_desc'] = 'Student must achieve a passing grade of {$a}%';
$string['completionallquizzes_desc'] = 'Student must answer all quiz questions in the video';
$string['autocompleteson'] = 'This activity will automatically complete on: {$a}';

$string['timespent'] = 'Time spent so far';
$string['timeremaining'] = 'Time remaining';
$string['hour'] = 'Hours';
$string['minute'] = 'Minutes';
$string['second'] = 'Seconds';
$string['complete'] = 'Completed!';
$string['incomplete'] = 'Not yet completed';
$string['norequiredtimeset'] = 'No required time has been set.';
$string['activityprogress'] = 'Activity Progress';
$string['report'] = 'Quiz report';
$string['question'] = 'Question';
$string['quizretake'] = 'Retake Quiz';