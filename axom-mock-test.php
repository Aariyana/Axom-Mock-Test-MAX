<?php
/*
Plugin Name: Axom Mock Test MAX (Pro)
Description: Advanced Exam Platform with Bulk Import, Question Management & Leaderboard.
Version: 8.5
Author: Axom Xarothi
*/

if (!defined('ABSPATH')) exit;

// ================= ১. ডাটাবেছ ছেটআপ (অসমীয়া আখৰৰ বাবে সমৰ্থিত) =================
register_activation_hook(__FILE__, 'axom_db_setup');
function axom_db_setup() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // UTF8MB4 ব্যৱহাৰ কৰা হৈছে যাতে অসমীয়া আখৰবোৰ '????' হৈ নাযায়
    $charset_collate = "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

    $q_table = $wpdb->prefix . 'axom_questions';
    $l_table = $wpdb->prefix . 'axom_leaderboard';

    $sql1 = "CREATE TABLE $q_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question TEXT NOT NULL,
        option_a TEXT, option_b TEXT, option_c TEXT, option_d TEXT,
        correct CHAR(1),
        category VARCHAR(50)
    ) $charset_collate;";

    $sql2 = "CREATE TABLE $l_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        score FLOAT,
        correct INT,
        wrong INT,
        created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    dbDelta($sql1);
    dbDelta($sql2);
}

// ================= ২. এডমিন মেনু আৰু ড্যাশবৰ্ড =================
add_action('admin_menu', function() {
    add_menu_page('Axom Exam', 'Axom Exam', 'manage_options', 'axom-exam', 'axom_admin_page', 'dashicons-welcome-learn-more');
});

function axom_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'axom_questions';

    // ১. এটা প্ৰশ্ন ডিলিট কৰা
    if (isset($_GET['delete_id'])) {
        $wpdb->delete($table, ['id' => intval($_GET['delete_id'])]);
        echo "<div class='updated'><p>🗑️ Question Deleted!</p></div>";
    }

    // ২. সকলো প্ৰশ্ন একেলগে ডিলিট কৰা
    if (isset($_POST['delete_all'])) {
        $wpdb->query("TRUNCATE TABLE $table");
        echo "<div class='error'><p>⚠️ All Questions Cleared!</p></div>";
    }

    // ৩. Bulk Import Logic (অসমীয়া আখৰ সুৰক্ষিত ৰাখি)
    if (isset($_POST['bulk_import']) && !empty($_POST['bulk_data'])) {
        $lines = explode("\n", trim($_POST['bulk_data']));
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            if (count($row) >= 6) {
                $wpdb->insert($table, [
                    'question' => sanitize_textarea_field($row[0]),
                    'option_a' => sanitize_text_field($row[1]),
                    'option_b' => sanitize_text_field($row[2]),
                    'option_c' => sanitize_text_field($row[3]),
                    'option_d' => sanitize_text_field($row[4]),
                    'correct'  => strtolower(trim($row[5])),
                    'category' => isset($row[6]) ? sanitize_text_field($row[6]) : 'General'
                ]);
            }
        }
        echo "<div class='updated'><p>✅ Questions Imported Successfully!</p></div>";
    }

    // --- এডমিন UI আৰম্ভ ---
    echo '<div class="wrap"><h1>Axom Mock Test Pro Dashboard</h1>';
    
    // Bulk Import Box
    echo '<div style="background:#fff; padding:20px; border-radius:10px; margin-bottom:20px; border:1px solid #ccd0d4; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
    <h3>Bulk Import Questions (CSV Format)</h3>
    <p>Format: Question,A,B,C,D,Correct(a/b/c/d),Category</p>
    <form method="post"><textarea name="bulk_data" style="width:100%; height:120px; border:2px solid #ddd; padding:10px;" placeholder="Example: শৰাইঘাটৰ যুদ্ধ কেতিয়া হৈছিল?,১৬৭১,১৬৬১,১৭০০,১৮২৬,a,History"></textarea><br><br>
    <input type="submit" name="bulk_import" class="button button-primary" value="Import Questions"></form></div>';

    // Manage Questions Table
    $questions = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
    
    echo '<div style="background:#fff; padding:20px; border-radius:10px; border:1px solid #ccd0d4;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h3>Manage Questions (' . count($questions) . ')</h3>
        <form method="post" onsubmit="return confirm(\'Are you sure you want to delete ALL questions?\');">
            <input type="submit" name="delete_all" class="button button-link-delete" value="Delete All Questions" style="color:#d9534f; font-weight:bold;">
        </form>
    </div>';

    if ($questions) {
        echo '<table class="wp-list-table widefat fixed striped">
        <thead><tr><th width="40%">Question</th><th>Options</th><th width="10%">Correct</th><th>Action</th></tr></thead><tbody>';
        foreach ($questions as $q) {
            $del_url = admin_url('admin.php?page=axom-exam&delete_id=' . $q->id);
            echo "<tr>
                <td><strong>" . esc_html($q->question) . "</strong></td>
                <td>A: {$q->option_a} | B: {$q->option_b} | C: {$q->option_c} | D: {$q->option_d}</td>
                <td style='color:green; font-weight:bold;'>" . strtoupper($q->correct) . "</td>
                <td><a href='$del_url' class='button' onclick='return confirm(\"Delete this?\")' style='color:red;'>Delete</a></td>
            </tr>";
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No questions found. Use Bulk Import above.</p>';
    }
    echo '</div></div>';
}

// ================= ৩. ফ্ৰন্টএণ্ড (Mock Test Page) =================
add_shortcode('axom_mock_test', function() {
    global $wpdb;
    // প্ৰতিবাৰ ৫০ টা প্ৰশ্ন বেলেগকৈ (Randomly) ওলাব
    $qs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}axom_questions ORDER BY RAND() LIMIT 50");
    if (!$qs) return "<p style='color:red;'>প্ৰশ্ন বিচাৰি পোৱা নগ'ল। অনুগ্ৰহ কৰি এডমিনত প্ৰশ্ন যোগ কৰক।</p>";

    ob_start(); ?>
    <style>
        .axom-box { max-width: 800px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-top: 10px solid #002D62; font-family: sans-serif; }
        .timer-header { position: sticky; top: 0; background: #002D62; color: #fff; padding: 15px; text-align: center; border-radius: 8px; font-weight: bold; font-size: 20px; z-index: 1000; box-shadow: 0 4px 10px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .q-card { background: #f9fbff; padding: 20px; margin-bottom: 20px; border-radius: 10px; border-left: 5px solid #002D62; border: 1px solid #e1e8f0; }
        label { display: block; padding: 12px; margin: 8px 0; background: #fff; border-radius: 6px; cursor: pointer; border: 1px solid #ddd; transition: 0.3s; }
        label:hover { background: #eef2ff; border-color: #002D62; }
        .submit-btn { background: #d9534f; color: white; border: none; padding: 15px 40px; width: 100%; border-radius: 30px; font-size: 20px; font-weight: bold; cursor: pointer; transition: 0.3s; box-shadow: 0 5px 15px rgba(217,83,79,0.3); }
        .submit-btn:hover { background: #c9302c; transform: scale(1.02); }
        #res-box { display:none; text-align:center; padding:40px; border:3px solid #002D62; border-radius:15px; }
    </style>

    <div id="quiz_wrapper">
        <div class="axom-box">
            <div class="timer-header" id="axom_timer">Time Left: 30:00</div>
            <input type="text" id="p_name" placeholder="প্ৰাৰ্থীৰ নাম লিখক (Enter Full Name)" style="width:100%; padding:15px; margin-bottom:20px; border:2px solid #ddd; border-radius:8px; font-size:16px;">
            <form id="axom_quiz_form">
                <?php foreach ($qs as $k => $q): ?>
                    <div class="q-card">
                        <p><strong>Q<?php echo $k+1; ?>. <?php echo esc_html($q->question); ?></strong></p>
                        <label><input type="radio" name="q<?php echo $q->id; ?>" value="a"> <?php echo esc_html($q->option_a); ?></label>
                        <label><input type="radio" name="q<?php echo $q->id; ?>" value="b"> <?php echo esc_html($q->option_b); ?></label>
                        <label><input type="radio" name="q<?php echo $q->id; ?>" value="c"> <?php echo esc_html($q->option_c); ?></label>
                        <label><input type="radio" name="q<?php echo $q->id; ?>" value="d"> <?php echo esc_html($q->option_d); ?></label>
                    </div>
                <?php endforeach; ?>
                <button type="button" class="submit-btn" onclick="submitAxomQuiz()">Submit Exam</button>
            </form>
        </div>
    </div>

    <div id="res-box" class="axom-box">
        <h1 style="color:#002D62;">Exam Result</h1>
        <div id="score_display" style="font-size:35px; margin:25px 0; font-weight:bold;"></div>
        <button onclick="window.location.reload()" style="padding:10px 20px; background:#002D62; color:white; border:none; border-radius:5px; cursor:pointer;">Retake Exam</button>
    </div>

    <script>
        let tRem = 1800; // ৩০ মিনিট
        let quizTimer = setInterval(() => {
            tRem--;
            let m = Math.floor(tRem / 60), s = tRem % 60;
            document.getElementById('axom_timer').innerHTML = "Time Left: " + m + ":" + (s < 10 ? '0' : '') + s;
            if (tRem <= 0) submitAxomQuiz();
        }, 1000);

        function submitAxomQuiz() {
            clearInterval(quizTimer);
            let sc = 0, cr = 0, wr = 0;
            let qSet = <?php echo json_encode($qs); ?>;
            
            qSet.forEach(q => {
                let sel = document.querySelector(`input[name="q${q.id}"]:checked`);
                if (sel) {
                    if (sel.value === q.correct) { sc += 1; cr++; }
                    else { sc -= 0.25; wr++; }
                }
            });

            document.getElementById('quiz_wrapper').style.display = 'none';
            document.getElementById('res-box').style.display = 'block';
            document.getElementById('score_display').innerHTML = `Candidate: ${document.getElementById('p_name').value || 'Guest'}<br><span style="color:green;">Correct: ${cr}</span> | <span style="color:red;">Wrong: ${wr}</span><br>Total Score: ${sc}`;
            
            // AJAX Save
            let fd = new FormData();
            fd.append('action', 'save_axom_score');
            fd.append('name', document.getElementById('p_name').value);
            fd.append('score', sc);
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd });
        }
        window.onblur = function() { alert("পৰীক্ষাৰ সময়ত টেব সলনি নকৰিব!"); }
    </script>
    <?php return ob_get_clean();
});

// ================= ৪. ৰিজাল্ট ছেভ কৰা (AJAX) =================
add_action('wp_ajax_save_axom_score', 'save_axom_score');
add_action('wp_ajax_nopriv_save_axom_score', 'save_axom_score');
function save_axom_score() {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'axom_leaderboard', [
        'name' => sanitize_text_field($_POST['name']),
        'score' => floatval($_POST['score'])
    ]);
    wp_die();
}
