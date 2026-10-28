<?php
function default_leader_profiles() {
    return [
        [
            'id' => 'richard_awanda',
            'name' => 'Rev. Richard Awanda',
            'role' => 'Lead Pastor',
            'description' => 'Leads campus ministry with preaching, pastoral care, and a clear vision for discipleship and community formation.',
            'photo' => '',
        ],
        [
            'id' => 'charles_onyango',
            'name' => 'Rev. Charles Onyango',
            'role' => 'Associate Minister',
            'description' => 'Partners in teaching and student support while equipping teams for worship, outreach, and pastoral care.',
            'photo' => '',
        ],
        [
            'id' => 'youth_ministry',
            'name' => 'Youth Ministry Leader',
            'role' => 'Youth Ministry',
            'description' => 'Encourages young people through retreats, small groups, and spiritual formation that builds faith and leadership.',
            'photo' => '',
        ],
        [
            'id' => 'worship_ministry',
            'name' => 'Worship Ministry Leader',
            'role' => 'Praise & Worship',
            'description' => 'Guides our worship gatherings so music and prayer invite every heart into deeper devotion.',
            'photo' => '',
        ],
        [
            'id' => 'outreach_ministry',
            'name' => 'Outreach Ministry Leader',
            'role' => 'Compassionate Ministry',
            'description' => 'Leads compassionate action in our community, helping neighbors with practical care and gospel hope.',
            'photo' => '',
        ],
    ];
}

function load_leader_profiles(PDO $db) {
    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = :setting_key');
    $stmt->execute([':setting_key' => 'leadership_profiles']);
    $storedProfiles = $stmt->fetchColumn();

    if ($storedProfiles !== false && $storedProfiles !== null) {
        $profiles = json_decode($storedProfiles, true);
        if (!is_array($profiles)) {
            throw new RuntimeException('Saved leader profiles are invalid.');
        }

        return array_values(array_filter($profiles, static function ($profile) {
            return is_array($profile)
                && isset($profile['id'], $profile['name'], $profile['role'], $profile['description'], $profile['photo'])
                && preg_match('/\A[a-z0-9][a-z0-9_-]{0,49}\z/', (string) $profile['id']);
        }));
    }

    $profiles = default_leader_profiles();
    $photoKeys = array_map(static function ($profile) {
        return 'leader_photo_' . $profile['id'];
    }, $profiles);
    $placeholders = implode(', ', array_fill(0, count($photoKeys), '?'));
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ({$placeholders})");
    $stmt->execute($photoKeys);
    $photos = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (preg_match('/\Auploads\/leaders\/[A-Za-z0-9._-]+\z/', (string) $row['setting_value'])) {
            $photos[substr($row['setting_key'], strlen('leader_photo_'))] = $row['setting_value'];
        }
    }

    foreach ($profiles as &$profile) {
        $profile['photo'] = $photos[$profile['id']] ?? '';
    }
    unset($profile);

    return $profiles;
}

function save_leader_profiles(PDO $db, array $profiles) {
    $value = json_encode(array_values($profiles), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([':setting_key' => 'leadership_profiles', ':setting_value' => $value]);
}
