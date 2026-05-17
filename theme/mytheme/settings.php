<?php
// theme/mytheme/settings.php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    
    $settings = new theme_boost_admin_settingspage_tabs('themesettingmytheme', get_string('configtitle', 'theme_mytheme'));
    
    // ==========================================
    // TAB 1: FRONTPAGE SETTINGS
    // ==========================================
    $page = new admin_settingpage('theme_mytheme_frontpage', get_string('frontpagesettings', 'theme_mytheme'));
    
    // ===== BRAND COLORS =====
    $page->add(new admin_setting_heading('theme_mytheme/brandcolors', 
        '🎨 BRAND COLORS', 
        'Global primary and secondary colors'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/primarycolor',
        'Primary Color', 'Main brand color', '#5751E1'));
        
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/secondarycolor',
        'Secondary Color', 'Secondary brand color', '#FFC224'));

    // ===== HERO SECTION =====
    $page->add(new admin_setting_heading('theme_mytheme/heroheading', 
        '🏠 HERO SECTION', 
        'Main banner settings'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/herotagline',
        'Hero Tagline', 'Small tagline above heading', 'PROFESSIONAL COURSES'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/herotaglinecolor',
        'Tagline Color', 'Color for tagline', '#FFD700'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/herotitle',
        'Hero Title', 'Main heading', 'Find Business Courses & Develop Your Skills'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/herotitlecolor',
        'Title Color', 'Color for title', '#ffffff'));
    
    $page->add(new admin_setting_configtextarea('theme_mytheme/herodescription',
        'Hero Description', 'Description text', 
        "Free & Premium online courses from the world's best instructors. Join 17 million learners today."));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/herodesccolor',
        'Description Color', 'Color for description', '#ffffff'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/herooverlaycolor',
        'Overlay Color', 'Dark overlay (rgba format)', 'rgba(0,0,0,0.55)'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/herosearchplaceholder',
        'Search Placeholder', 'e.g., Search Here...', 'Search Here...'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/herosearchbutton',
        'Search Button Text', 'e.g., Find Courses', 'Find Courses'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/herosearchbtnbg',
        'Search Button Background', 'Button color', '#0b0557'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/herosearchbtntext',
        'Search Button Text Color', 'Text color', '#ffffff'));
    
    // Hero Image
    $heroimg = new admin_setting_configstoredfile('theme_mytheme/heroimage',
        'Hero Background Image', 'Upload hero background image', 'heroimage');
    $heroimg->set_updatedcallback('theme_reset_all_caches');
    $page->add($heroimg);
    
    // ===== STATS SECTION =====
    $page->add(new admin_setting_heading('theme_mytheme/statsheading', 
        '📊 STATS SECTION', 
        'Statistics counter section'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/statsbg',
        'Background Color', 'Section background', '#F9F9F9'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/stat1label',
        'Stat 1 Label', 'e.g., Learn Skills With', 'Learn Skills With'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/stat2label',
        'Stat 2 Label', 'e.g., Choose Courses', 'Choose Courses'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/stat3label',
        'Stat 3 Label', 'e.g., Professional Tutors', 'Professional Tutors'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/stat4label',
        'Stat 4 Label', 'e.g., Online Degrees', 'Online Degrees'));
        
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/stat1color',
        'Stat 1 Icon Color', '', '#1a56db'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/stat2color',
        'Stat 2 Icon Color', '', '#198754'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/stat3color',
        'Stat 3 Icon Color', '', '#ffc107'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/stat4color',
        'Stat 4 Icon Color', '', '#0dcaf0'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/statlabelcolor',
        'Global Stat Label Color', 'Text color for stats', '#000000'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/stat1labelcolor',
        'Stat 1 Label Color', '', '#000000'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/stat2labelcolor',
        'Stat 2 Label Color', '', '#000000'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/stat3labelcolor',
        'Stat 3 Label Color', '', '#000000'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/stat4labelcolor',
        'Stat 4 Label Color', '', '#000000'));
    
    // ===== POPULAR COURSES SECTION =====
    $page->add(new admin_setting_heading('theme_mytheme/coursesheading', 
        '📚 POPULAR COURSES SECTION', 
        'Courses display settings'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/coursesbg',
        'Background Color', 'Section background', '#F9F9F9'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/coursessubtitle',
        'Subtitle Text', 'Above heading', '+ unique online courses'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/coursessubtitlecolor',
        'Subtitle Color', 'Subtitle color', '#5751E1'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/coursestitle',
        'Title Text', 'Main heading', 'Our Most Popular Courses'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/coursestitlecolor',
        'Title Color', 'Title color', '#000000'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/coursesbutton',
        'Button Text', 'Discover all button', 'Discover All Courses'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/courseratingtext',
        'Rating Text', 'After stars', 'Reviews'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/courselessonstext',
        'Lessons Text', 'Label for lessons', 'Lessons'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/coursestudentstext',
        'Students Text', 'Label for students', 'Students'));
        
    $page->add(new admin_setting_configcheckbox('theme_mytheme/courses_showrating',
        'Show Rating Stars', 'Show rating stars on course cards', 1));
        
    $page->add(new admin_setting_configcheckbox('theme_mytheme/courses_showprice',
        'Show Course Price', 'Show course price on cards', 1));
    
    // Course Display Controls
    $page->add(new admin_setting_heading('theme_mytheme/coursesdisplay', 
        '📋 COURSE DISPLAY CONTROLS', 
        'How many courses to show and layout'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/coursescount',
        'Number of Courses to Show', 'Total courses to display (e.g., 4, 8, 12)', '12'));
    
    $page->add(new admin_setting_configselect('theme_mytheme/coursesperrow',
        'Courses Per Row (Desktop)', 'How many course cards in one row',
        '4', ['2' => '2 per row', '3' => '3 per row', '4' => '4 per row', '6' => '6 per row']));
    
    $page->add(new admin_setting_configselect('theme_mytheme/coursesperrowtablet',
        'Courses Per Row (Tablet)', 'How many cards per row on tablet',
        '3', ['1' => '1 per row', '2' => '2 per row', '3' => '3 per row', '4' => '4 per row']));
    
    $page->add(new admin_setting_configselect('theme_mytheme/coursesperrowmobile',
        'Courses Per Row (Mobile)', 'How many cards per row on mobile',
        '1', ['1' => '1 per row', '2' => '2 per row']));
    
    
    $page->add(new admin_setting_configselect('theme_mytheme/coursesoverflow',
        'If More Courses Than Display Count', 'How to handle extra courses',
        'scroll', [
            'scroll' => 'Show All - Scroll down',
            'slider' => 'Show as Slider/Carousel'
        ]));

    $page->add(new admin_setting_heading('theme_mytheme/coursesfeatured', 
        '🎯 FEATURED COURSES (MANUAL PRICE & STARS)', 
        'Enter Course IDs and their custom Price and Stars (0-5). Leave ID empty to show default courses.'));

    $page->add(new admin_setting_configtext('theme_mytheme/course1_id', 'Course 1 ID', '', ''));
    $page->add(new admin_setting_configtext('theme_mytheme/course1_price', 'Course 1 Price', 'e.g. 55.00', '55.00'));
    $page->add(new admin_setting_configselect('theme_mytheme/course1_stars', 'Course 1 Stars', '', '5', 
        ['1' => '1 Star', '2' => '2 Stars', '3' => '3 Stars', '4' => '4 Stars', '5' => '5 Stars']));

    $page->add(new admin_setting_configtext('theme_mytheme/course2_id', 'Course 2 ID', '', ''));
    $page->add(new admin_setting_configtext('theme_mytheme/course2_price', 'Course 2 Price', 'e.g. 70.00', '70.00'));
    $page->add(new admin_setting_configselect('theme_mytheme/course2_stars', 'Course 2 Stars', '', '5', 
        ['1' => '1 Star', '2' => '2 Stars', '3' => '3 Stars', '4' => '4 Stars', '5' => '5 Stars']));

    $page->add(new admin_setting_configtext('theme_mytheme/course3_id', 'Course 3 ID', '', ''));
    $page->add(new admin_setting_configtext('theme_mytheme/course3_price', 'Course 3 Price', 'e.g. 45.00', '45.00'));
    $page->add(new admin_setting_configselect('theme_mytheme/course3_stars', 'Course 3 Stars', '', '5', 
        ['1' => '1 Star', '2' => '2 Stars', '3' => '3 Stars', '4' => '4 Stars', '5' => '5 Stars']));

    $page->add(new admin_setting_configtext('theme_mytheme/course4_id', 'Course 4 ID', '', ''));
    $page->add(new admin_setting_configtext('theme_mytheme/course4_price', 'Course 4 Price', 'e.g. 62.00', '62.00'));
    $page->add(new admin_setting_configselect('theme_mytheme/course4_stars', 'Course 4 Stars', '', '5', 
        ['1' => '1 Star', '2' => '2 Stars', '3' => '3 Stars', '4' => '4 Stars', '5' => '5 Stars']));
    
    // ===== CTA BANNER SECTION =====
    $page->add(new admin_setting_heading('theme_mytheme/ctaheading', 
        '🎯 CTA BANNER SECTION', 
        'Middle call-to-action banner'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/ctabg',
        'Background Color', 'Banner background', '#0B0B3B'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/ctatitle',
        'Title Text', 'Banner heading', 'Finding Your Right Courses'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/ctatitlecolor',
        'Title Color', 'Title color', '#ffffff'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/ctadescription',
        'Description Text', 'Banner description', 'Intuitive Shared Inbox Makes It Easy For Team Member'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/ctadesccolor',
        'Description Color', 'Description color', '#cccccc'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/ctabutton',
        'Button Text', 'CTA button', 'GET STARTED'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/ctabtnbg',
        'Button Background', 'Button color', '#FFC224'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/ctabtntext',
        'Button Text Color', 'Button text', '#0B0B3B'));
    
    // CTA Image
    $ctaimg = new admin_setting_configstoredfile('theme_mytheme/ctaimage',
        'Banner Image', 'Upload student image', 'ctaimage');
    $ctaimg->set_updatedcallback('theme_reset_all_caches');
    $page->add($ctaimg);
    
    // ===== WHY CHOOSE US SECTION =====
    $page->add(new admin_setting_heading('theme_mytheme/aboutheading', 
        '💡 WHY CHOOSE US SECTION', 
        'About/features section'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/aboutbg',
        'Background Color', 'Section background', '#ffffff'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/aboutbadge',
        'Badge Text', 'Above heading', 'Why Choose Us'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/aboutbadgebg',
        'Badge Background', 'Badge bg color', '#f0f2f5'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/aboutbadgecolor',
        'Badge Text Color', 'Badge text color', '#1a56db'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/abouttitle',
        'Title Text', 'Main heading', 'Professional Courses Taught By Industry Leaders'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/abouttitlecolor',
        'Title Color', 'Title color', '#000000'));
    
    $page->add(new admin_setting_configtextarea('theme_mytheme/aboutdescription',
        'Description Text', 'Description paragraph', 
        "Groove's intuitive shared inbox makes it easy for team members to organize."));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/aboutdesccolor',
        'Description Color', 'Description text color', '#555555'));
    
    $page->add(new admin_setting_configtextarea('theme_mytheme/aboutpoints',
        'Bullet Points', 'One per line', 
        "Body & Mind Stress Relief\nEnhance Strength Growing\nGet Better Posture"));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/aboutpointcolor',
        'Bullet Point Color', 'Text color', '#333333'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/aboutbutton',
        'Button Text', 'CTA button', 'Start Free Trial'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/aboutbtnbg',
        'Button Background', 'Button color', '#5751E1'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/aboutbtntext',
        'Button Text Color', 'Button text', '#ffffff'));
    
    // About Image
    $aboutimg = new admin_setting_configstoredfile('theme_mytheme/aboutimage',
        'About Image', 'Upload about image', 'aboutimage');
    $aboutimg->set_updatedcallback('theme_reset_all_caches');
    $page->add($aboutimg);
    
    // ===== CATEGORIES SECTION =====
    $page->add(new admin_setting_heading('theme_mytheme/categoriesheading', 
        '📁 CATEGORIES SECTION', 
        'Course categories display'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/categoriesbg',
        'Background Color', 'Section background', '#F9F9F9'));
    
    // Categories Background Image
    $catbg = new admin_setting_configstoredfile('theme_mytheme/categoriesbgimage',
        'Background Image', 'Upload background image', 'categoriesbg');
    $catbg->set_updatedcallback('theme_reset_all_caches');
    $page->add($catbg);
    
    $page->add(new admin_setting_configtext('theme_mytheme/categoriesbadge',
        'Badge Text', 'Above heading', 'Our Top Categories'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/categoriesbadgebg',
        'Badge Background', 'Badge bg color', '#ffffff'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/categoriesbadgecolor',
        'Badge Text Color', 'Badge text color', '#1a56db'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/categoriestitle',
        'Title Text', 'Main heading', 'Your Creative And Passionate Business Coach'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/categoriestitlecolor',
        'Title Color', 'Title color', '#000000'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/categoriescoursetext',
        'Course Count Text', 'After number', 'Courses'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/categoriescardbg',
        'Card Background', 'Card bg color', '#ffffff'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/categoriesiconcolor',
        'Icon Color', 'Category icon color', '#1a56db'));
    
    // Categories Display Controls
    $page->add(new admin_setting_heading('theme_mytheme/categoriesdisplay', 
        '📋 CATEGORIES DISPLAY CONTROLS', 
        'How many categories to show'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/categoriescount',
        'Number of Categories', 'Total to display (e.g., 4, 8, 12)', '8'));
    
    $page->add(new admin_setting_configselect('theme_mytheme/categoriesperrow',
        'Categories Per Row (Desktop)', 'How many per row',
        '4', ['2' => '2 per row', '3' => '3 per row', '4' => '4 per row', '6' => '6 per row']));
    
    
    $page->add(new admin_setting_configselect('theme_mytheme/categoriesoverflow',
        'Overflow Handling', 'Extra categories handling',
        'scroll', ['scroll' => 'Scroll', 'slider' => 'Slider']));

    $page->add(new admin_setting_heading('theme_mytheme/categoriesselection', 
        '🎯 SPECIFIC CATEGORIES SELECTION', 
        'Leave empty to show all. Enter Category IDs.'));

    $page->add(new admin_setting_configtext('theme_mytheme/cat1_id', 'Category 1 ID', '', ''));
    $page->add(new admin_setting_configtext('theme_mytheme/cat2_id', 'Category 2 ID', '', ''));
    $page->add(new admin_setting_configtext('theme_mytheme/cat3_id', 'Category 3 ID', '', ''));
    $page->add(new admin_setting_configtext('theme_mytheme/cat4_id', 'Category 4 ID', '', ''));
    
    // ===== INSTRUCTORS SECTION =====
    $page->add(new admin_setting_heading('theme_mytheme/instructorsheading', 
        '👨‍🏫 INSTRUCTORS SECTION', 
        'Expert instructors display'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/instructorsbg',
        'Background Color', 'Section background', '#ffffff'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/instructorsbadge',
        'Badge Text', 'Above heading', 'Skilled Introduce'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/instructorstitle',
        'Title Text', 'Main heading', 'Our Top Class & Expert Instructors'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/instructorstitlecolor',
        'Title Color', 'Title color', '#000000'));
    
    $page->add(new admin_setting_configtextarea('theme_mytheme/instructorsdescription',
        'Description Text', 'Description paragraph', 'when an unknown printer took a galley of type and scrambled.'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/instructorsbutton',
        'Button Text', 'See All button', 'See All Instructors'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/instructorsbtncolor',
        'Button Text Color', 'Button color', '#1a56db'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/instructorsratingtext',
        'Rating Text', 'After stars', 'Ratings'));
    
    // Instructor Display Controls
    $page->add(new admin_setting_heading('theme_mytheme/instructorsdisplay', 
        '📋 INSTRUCTORS DISPLAY CONTROLS', 
        'Teacher IDs, count, layout'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/instructorscount',
        'Number of Instructors', 'Total to display (e.g., 4, 8)', '4'));
    
    $page->add(new admin_setting_configselect('theme_mytheme/instructorsperrow',
        'Instructors Per Row (Desktop)', 'How many per row',
        '2', ['1' => '1 per row', '2' => '2 per row', '3' => '3 per row', '4' => '4 per row']));
    
    $page->add(new admin_setting_configselect('theme_mytheme/instructorsoverflow',
        'Overflow Handling', 'Extra instructors',
        'scroll', ['scroll' => 'Scroll', 'slider' => 'Slider']));

    $page->add(new admin_setting_heading('theme_mytheme/instructorsselection', 
        '🎯 SPECIFIC TEACHERS SELECTION', 
        'Enter Teacher User IDs and their Stars (0-5).'));

    $page->add(new admin_setting_configtext('theme_mytheme/teacher1_id', 'Teacher 1 ID', '', ''));
    $page->add(new admin_setting_configselect('theme_mytheme/teacher1_stars', 'Teacher 1 Stars', '', '5', 
        ['1' => '1 Star', '2' => '2 Stars', '3' => '3 Stars', '4' => '4 Stars', '5' => '5 Stars']));
    
    $page->add(new admin_setting_configtext('theme_mytheme/teacher2_id', 'Teacher 2 ID', '', ''));
    $page->add(new admin_setting_configselect('theme_mytheme/teacher2_stars', 'Teacher 2 Stars', '', '5', 
        ['1' => '1 Star', '2' => '2 Stars', '3' => '3 Stars', '4' => '4 Stars', '5' => '5 Stars']));
    
    $page->add(new admin_setting_configtext('theme_mytheme/teacher3_id', 'Teacher 3 ID', '', ''));
    $page->add(new admin_setting_configselect('theme_mytheme/teacher3_stars', 'Teacher 3 Stars', '', '5', 
        ['1' => '1 Star', '2' => '2 Stars', '3' => '3 Stars', '4' => '4 Stars', '5' => '5 Stars']));
    
    $page->add(new admin_setting_configtext('theme_mytheme/teacher4_id', 'Teacher 4 ID', '', ''));
    $page->add(new admin_setting_configselect('theme_mytheme/teacher4_stars', 'Teacher 4 Stars', '', '5', 
        ['1' => '1 Star', '2' => '2 Stars', '3' => '3 Stars', '4' => '4 Stars', '5' => '5 Stars']));
    
    // ===== TESTIMONIALS SECTION =====
    $page->add(new admin_setting_heading('theme_mytheme/testimonialsheading', 
        '💬 TESTIMONIALS SECTION', 
        'Client testimonials'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/testimonialbg',
        'Background Color', 'Section background', '#ffffff'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/testimonialbadge',
        'Badge Text', 'Above heading', 'Testimonials'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/testimonialtitle',
        'Title Text', 'Main heading', "What's Our Client Say About Us"));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/testimonialtitlecolor',
        'Title Color', 'Title color', '#000000'));
    
    $page->add(new admin_setting_configtextarea('theme_mytheme/testimonialtext',
        'Testimonial Quote', 'Client quote', 'Manage and streamline operations across multiple locations.'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/testimonialtextcolor',
        'Quote Color', 'Quote text color', '#555555'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/testimonialname',
        'Client Name', 'Name', 'Brooklyn Simmons'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/testimonialrole',
        'Client Role', 'Role/title', 'Engineer'));
        
    $page->add(new admin_setting_configselect('theme_mytheme/testimonial_stars',
        'Testimonial Stars', '', '5', 
        ['1' => '1 Star', '2' => '2 Stars', '3' => '3 Stars', '4' => '4 Stars', '5' => '5 Stars']));
    
    // Testimonial Image
    $testimg = new admin_setting_configstoredfile('theme_mytheme/testimonialimage',
        'Client Image', 'Upload client image', 'testimonialimage');
    $testimg->set_updatedcallback('theme_reset_all_caches');
    $page->add($testimg);
    
    // ===== BRANDS SECTION =====
    $page->add(new admin_setting_heading('theme_mytheme/brandsheading', 
        '🏢 BRANDS SECTION', 
        'Partner/client logos'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/brandssectionbg',
        'Background Color', 'Section background', 'transparent'));
    
    $page->add(new admin_setting_configstoredfile('theme_mytheme/brand1', 'Brand Logo 1', 'Upload', 'brand1'));
    $page->add(new admin_setting_configstoredfile('theme_mytheme/brand2', 'Brand Logo 2', 'Upload', 'brand2'));
    $page->add(new admin_setting_configstoredfile('theme_mytheme/brand3', 'Brand Logo 3', 'Upload', 'brand3'));
    $page->add(new admin_setting_configstoredfile('theme_mytheme/brand4', 'Brand Logo 4', 'Upload', 'brand4'));
    $page->add(new admin_setting_configstoredfile('theme_mytheme/brand5', 'Brand Logo 5', 'Upload', 'brand5'));
    $page->add(new admin_setting_configstoredfile('theme_mytheme/brand6', 'Brand Logo 6', 'Upload', 'brand6'));
    
    // ===== NEWS & BLOG SECTION =====
    $page->add(new admin_setting_heading('theme_mytheme/blogheading', 
        '📰 NEWS & BLOG SECTION', 
        'Latest blog posts display'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/blogsectionbg',
        'Background Color', 'Section background', '#F8F8F8'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/blogbadge',
        'Badge Text', 'Above heading', 'News & Blogs'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/blogbadgebg',
        'Badge Background', 'Badge bg color', '#f0f2f5'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/blogbadgecolor',
        'Badge Text Color', 'Badge text color', '#1a56db'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/blogtitle',
        'Title Text', 'Main heading', 'Our Latest News Feed'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/blogtitlecolor',
        'Title Color', 'Title color', '#000000'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/blogdescription',
        'Description Text', 'Subtitle', 'when known printer took a gallery of type scramble edmake'));
    
    // Blog Display Controls
    $page->add(new admin_setting_heading('theme_mytheme/blogdisplay', 
        '📋 BLOG DISPLAY CONTROLS', 
        'How many blogs, layout, IDs'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/blogcount',
        'Number of Blogs', 'Total to display (e.g., 3, 6, 9)', '3'));
    
    $page->add(new admin_setting_configselect('theme_mytheme/blogperrow',
        'Blogs Per Row (Desktop)', 'How many per row',
        '3', ['1' => '1 per row', '2' => '2 per row', '3' => '3 per row', '4' => '4 per row']));
    
    
    $page->add(new admin_setting_configselect('theme_mytheme/blogoverflow',
        'Overflow Handling', 'Extra blogs',
        'scroll', ['scroll' => 'Scroll', 'slider' => 'Slider']));
    
    // ===== EVENTS SECTION =====
    $page->add(new admin_setting_heading('theme_mytheme/eventsheading', 
        '📅 EVENTS SECTION', 
        'Upcoming events display'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/eventssectionbg',
        'Background Color', 'Section background', '#F8F8F8'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/eventsbadge',
        'Badge Text', 'Above heading', '📅 Events'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/eventsbadgebg',
        'Badge Background', 'Badge bg color', '#f0f2f5'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/eventsbadgecolor',
        'Badge Text Color', 'Badge text color', '#1a56db'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/eventstitle',
        'Title Text', 'Main heading', 'Our Latest Events'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/eventstitlecolor',
        'Title Color', 'Title color', '#000000'));
    
    // Events Display Controls
    $page->add(new admin_setting_heading('theme_mytheme/eventsdisplay', 
        '📋 EVENTS DISPLAY CONTROLS', 
        'How many events, layout, IDs'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/eventscount',
        'Number of Events', 'Total to display (e.g., 3, 6)', '3'));
    
    $page->add(new admin_setting_configselect('theme_mytheme/eventsperrow',
        'Events Per Row (Desktop)', 'How many per row',
        '3', ['1' => '1 per row', '2' => '2 per row', '3' => '3 per row', '4' => '4 per row']));
    
    
    $page->add(new admin_setting_configselect('theme_mytheme/eventsoverflow',
        'Overflow Handling', 'Extra events',
        'scroll', ['scroll' => 'Scroll', 'slider' => 'Slider']));

    $page->add(new admin_setting_heading('theme_mytheme/eventsselection', 
        '🎯 EVENT CATEGORIES SELECTION', 
        'Enter Event Category IDs to filter events.'));

    $page->add(new admin_setting_configtext('theme_mytheme/event_cat1_id', 'Event Category 1 ID', '', ''));
    $page->add(new admin_setting_configtext('theme_mytheme/event_cat2_id', 'Event Category 2 ID', '', ''));
    $page->add(new admin_setting_configtext('theme_mytheme/event_cat3_id', 'Event Category 3 ID', '', ''));
    $page->add(new admin_setting_configtext('theme_mytheme/event_cat4_id', 'Event Category 4 ID', '', ''));
    
    // ===== BOTTOM CTA SECTION =====
    $page->add(new admin_setting_heading('theme_mytheme/bottomctaheading', 
        '🔔 BOTTOM CTA SECTION', 
        'Final call-to-action'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/bottomctabg',
        'Background Color', 'Section background', '#5751E1'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/bottomctatitle',
        'Title Text', 'Heading', 'Start Today And Get Certified!'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/bottomctatitlecolor',
        'Title Color', 'Title color', '#ffffff'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/bottomctabutton',
        'Button Text', 'CTA button', 'Get Started Now'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/bottomctabtnbg',
        'Button Background', 'Button color', '#ffffff'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/bottomctabtntext',
        'Button Text Color', 'Button text', '#000000'));
    
    $settings->add($page);
    
    // ==========================================
    // TAB 2: NAVBAR SETTINGS
    // ==========================================
    $page = new admin_settingpage('theme_mytheme_navbar', get_string('navbarsettings', 'theme_mytheme'));
    
    // ===== NAVBAR COLORS =====
    $page->add(new admin_setting_heading('theme_mytheme/navbarcolors', 
        '🎨 NAVBAR COLORS', 'Navigation bar color settings'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/navbarbg',
        'Navbar Background Color', 'Background color', '#ffffff'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/navbartextcolor',
        'Navbar Text Color', 'Link text color', '#5a5c69'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/navbarhovercolor',
        'Navbar Hover Color', 'Hover text color', '#1a56db'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/navbaractivecolor',
        'Navbar Active Color', 'Active link color', '#1a56db'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/navbarheight',
        'Navbar Height', 'e.g., 60px, 70px', '60px'));
    
    // ===== LOGO & BRANDING =====
    $page->add(new admin_setting_heading('theme_mytheme/navbarlogo', 
        '🖼️ LOGO & BRANDING', 'Upload logo'));
    
    $logosetting = new admin_setting_configstoredfile('theme_mytheme/logo',
        'Upload Logo', 'Custom logo image', 'logo');
    $logosetting->set_updatedcallback('theme_reset_all_caches');
    $page->add($logosetting);
    
    $page->add(new admin_setting_configtext('theme_mytheme/sitename',
        'Site Name', 'Empty = use default', ''));
    
    $page->add(new admin_setting_configtext('theme_mytheme/logowidth',
        'Logo Width', 'e.g., 150px, auto', 'auto'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/logoheight',
        'Logo Height', 'e.g., 40px, 35px', '40px'));
    
    // ===== NAVIGATION LINKS TEXT =====
    $page->add(new admin_setting_heading('theme_mytheme/navbarlinks', 
        '🔗 NAVIGATION LINKS TEXT', 'Customize menu text'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/navhome', 
        'Home Text', 'Home link', 'Home'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/navcourses', 
        'Courses Text', 'Courses link', 'Courses'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/navpages', 
        'Pages Text', 'Pages link', 'Pages'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/navdashboard', 
        'Dashboard Text', 'Dashboard link', 'Dashboard'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/navcategories', 
        'Categories Text', 'Categories button', 'Categories'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/navsearch', 
        'Search Placeholder', 'Search input', 'Search courses...'));
    
    // ===== NAVBAR BUTTONS =====
    $page->add(new admin_setting_heading('theme_mytheme/navbarbuttons', 
        '🔘 NAVBAR BUTTONS', 'CTA and login buttons'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/navtryfree', 
        'Try For Free Text', 'CTA button', 'Try For Free'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/navlogin', 
        'Login Text', 'Guest login button', 'Log in'));
    
    // ===== USER PROFILE DROPDOWN COLORS =====
    $page->add(new admin_setting_heading('theme_mytheme/userdropdowncolors', 
        '👤 USER PROFILE DROPDOWN COLORS', 'User menu dropdown styling'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/userdropdownbg',
        'Dropdown Background', 'Background color of dropdown menu', '#ffffff'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/userdropdownlinkcolor',
        'Dropdown Text Color', 'Color of text in dropdown', '#333333'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/userdropdownhoverbg',
        'Dropdown Hover Background', 'Background color when hovering', '#f0f2f5'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/userdropdownhovercolor',
        'Dropdown Hover Text Color', 'Text color when hovering', '#1a56db'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/useravatarbg',
        'Avatar Circle Background', 'Background color of user initials circle', '#1a56db'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/useravatartext',
        'Avatar Circle Text', 'Text color of user initials', '#ffffff'));
    
    $settings->add($page);
    
    // ==========================================
    // TAB 3: FOOTER SETTINGS
    // ==========================================
    $page = new admin_settingpage('theme_mytheme_footer', get_string('footersettings', 'theme_mytheme'));
    
    // ===== FOOTER COLORS =====
    $page->add(new admin_setting_heading('theme_mytheme/footercolors', 
        '🎨 FOOTER COLORS', 'Footer color scheme'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/footerbg', 
        'Footer Background', 'Background color', '#0B0B3B'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/footertextcolor', 
        'Footer Text Color', 'Text color', '#a8a8c8'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/footerheadingcolor', 
        'Footer Heading Color', 'Heading color', '#ffffff'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/footeraccent', 
        'Footer Accent Color', 'Icons, underlines', '#6259F3'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footerpadding', 
        'Footer Padding', 'e.g., 80px 0 10px 0', '80px 0 10px 0'));
    
    // ===== CONTACT INFORMATION =====
    $page->add(new admin_setting_heading('theme_mytheme/footercontact', 
        '📞 CONTACT INFORMATION', 'Contact details in footer'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footeremail', 
        'Email Address', 'Contact email', 'info@example.com'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footerphone', 
        'Phone Number', 'Contact phone', '+123 88 9900 456'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footeraddress', 
        'Address', 'Physical address', '201 S. Grand Ave., 1st Floor'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footercity', 
        'City/State/Zip', 'City state zip', 'New York City, NY 28020'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footercopyright', 
        'Copyright Text', 'Empty = auto-generate', ''));
    
    // ===== FOOTER COLUMNS =====
    $page->add(new admin_setting_heading('theme_mytheme/footercolumns', 
        '📋 FOOTER COLUMNS', 'Column headings and text'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footeruseful', 
        'Column 1 Heading', 'Useful Links', 'Useful Links'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footercompany', 
        'Column 2 Heading', 'Our Company', 'Our Company'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footernewsletter', 
        'Column 3 Heading', 'Newsletter', 'Newsletter SignUp!'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footernewsletterdesc', 
        'Newsletter Description', 'Below heading', 'Get the latest news delivered to your inbox'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footerfollow', 
        'Follow Us Text', 'Above icons', 'Follow Us:'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footersubscribe', 
        'Subscribe Button', 'Button text', 'Subscribe'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footeremailplaceholder', 
        'Email Placeholder', 'Input placeholder', 'Type your E-mail'));
    
    // ===== SUBSCRIBE BUTTON COLORS =====
    $page->add(new admin_setting_heading('theme_mytheme/subscribecolors', 
        '📧 SUBSCRIBE BUTTON COLORS', 'Newsletter subscribe button styling'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/subscribebtnbg',
        'Subscribe Button Background', 'Button color', '#6259F3'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/subscribebtntext',
        'Subscribe Button Text Color', 'Text color', '#ffffff'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/subscribebtnhover',
        'Subscribe Button Hover', 'Hover background', '#4a42d9'));
    
    // ===== FOOTER LINKS =====
    $page->add(new admin_setting_heading('theme_mytheme/footerlinks', 
        '🔗 FOOTER LINKS', 'Format: Text|URL per line'));
    
    $page->add(new admin_setting_configtextarea('theme_mytheme/footeremaillink',
        'Useful Links', 'One per line: Text|URL', 
        "All Courses|/course/\nMy Dashboard|/my/\nCalendar|/calendar/view.php\nPrivate Files|/user/files.php\nMy Badges|/badges/mybadges.php\nBlog|/blog/"));
    
    $page->add(new admin_setting_configtextarea('theme_mytheme/footercompanylinks',
        'Company Links', 'One per line: Text|URL', 
        "Contact Us|/user/contactsitesupport.php\nBrowse Courses|/course/\nSearch Courses|/course/search.php\nCreate Account|/login/signup.php"));
    
    // ===== SOCIAL MEDIA =====
    $page->add(new admin_setting_heading('theme_mytheme/footersocial', 
        '🌐 SOCIAL MEDIA', 'Leave empty to hide icon'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/facebook', 
        'Facebook URL', 'Leave empty to hide', '#'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/instagram', 
        'Instagram URL', 'Leave empty to hide', '#'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/linkedin', 
        'LinkedIn URL', 'Leave empty to hide', '#'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/pinterest', 
        'Pinterest URL', 'Leave empty to hide', '#'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/twitter', 
        'Twitter/X URL', 'Leave empty to hide', '#'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/youtube', 
        'YouTube URL', 'Leave empty to hide', '#'));
    
    // ===== SOCIAL MEDIA ICON COLORS =====
    $page->add(new admin_setting_heading('theme_mytheme/socialiconcolors', 
        '🎨 SOCIAL MEDIA ICON COLORS', 'Social media icon styling'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/socialiconcolor',
        'Icon Color', 'Default icon color', '#a8a8c8'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/socialiconhovercolor',
        'Icon Hover Color', 'Color when hovering', '#6259F3'));
    
    // ===== FOOTER BOTTOM BAR =====
    $page->add(new admin_setting_heading('theme_mytheme/footerbottom', 
        '📄 FOOTER BOTTOM BAR', 'Terms & Privacy'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footerterms', 
        'Terms Text', 'Terms link', 'Terms'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/footerprivacy', 
        'Privacy Text', 'Privacy link', 'Privacy'));
    
    $settings->add($page);
    
    // ==========================================
    // TAB 4: LOGIN PAGE SETTINGS
    // ==========================================
    $page = new admin_settingpage('theme_mytheme_login', 'Login Page');
    
    // ===== LOGIN PAGE STYLING =====
    $page->add(new admin_setting_heading('theme_mytheme/loginappearance', 
        '🎨 LOGIN PAGE STYLING', 'Login page colors and design'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/loginbg',
        'Page Background', 'Login page background (CSS hex or gradient)', 
        'linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/logincardbg',
        'Login Card Background', 'Card bg color', '#ffffff'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/loginbtnbg',
        'Login Button Background', 'Button color', '#1a56db'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/loginbtntext',
        'Login Button Text Color', 'Button text', '#ffffff'));
    
    // ===== LOGIN FORM COLORS =====
    $page->add(new admin_setting_heading('theme_mytheme/loginformcolors', 
        '📝 LOGIN FORM COLORS', 'Form input and label colors'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/loginheadingcolor',
        'Heading Color', 'Login heading color', '#1a56db'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/loginlabelcolor',
        'Label Color', 'Form label color', '#000000'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/logininputbg',
        'Input Background', 'Input field background', '#f8f9fa'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/logininputborder',
        'Input Border Color', 'Input field border color', '#e3e6f0'));
    
    $page->add(new admin_setting_configcolourpicker('theme_mytheme/logininputtext',
        'Input Text Color', 'Input field text color', '#333333'));
    
    // ===== LOGIN PAGE TEXT =====
    $page->add(new admin_setting_heading('theme_mytheme/logintexts', 
        '📝 LOGIN PAGE TEXT', 'Customize text labels'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/loginheading', 
        'Login Heading', 'Heading text', ''));
    
    $page->add(new admin_setting_configtext('theme_mytheme/forgotpasswordtext', 
        'Forgot Password Text', 'Forgot link', 'Forget password'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/guestlogintext', 
        'Guest Login Text', 'Guest section', 'Just browsing?'));
    
    $page->add(new admin_setting_configtext('theme_mytheme/guestbuttontext', 
        'Guest Button Text', 'Guest button', 'Login as Guest'));
    
    $settings->add($page);
}