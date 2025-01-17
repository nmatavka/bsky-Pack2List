<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');

if (isset($_POST['submit'])) {
    $BSKY_HANDLETEST = str_replace(["@", 'bsky.app'], ["", 'bsky.social'], $_POST['handle']);
    $BSKY_PWTEST = $_POST['apppassword'];
    $TARGET_HANDLE = str_replace(["@", 'bsky.app'], ["", 'bsky.social'], $_POST['target_handle']);
    $listType = $_POST['listType']; // followers or follows
    $filterType = $_POST['filterType']; // filter based on relationship

    require "./bsky-core.php";

    // Initialize the connection
    $pdsURL = getPDS($BSKY_HANDLETEST);
    if ($pdsURL == '') {
        echo 'Invalid Username Entered. Make sure it is the full name, including the domain suffix (e.g. user.bsky.social, not just user).';
    } else {
        $bluesky = new BlueskyApi($BSKY_HANDLETEST, $BSKY_PWTEST, $pdsURL);

        if ($bluesky->hasApiKey()) {
            // Get DID for the target user
            $args = [
                'actor' => $TARGET_HANDLE
            ];

            if ($tUsr = $bluesky->request('GET', 'app.bsky.actor.getProfile', $args)) {
                $tDID = $tUsr->did;
                $cursor = '';
                $arrFoll = [];
                $arrFollowings = [];

                // Fetch followers
                $cursor = '';
                do {
                    $args = ['actor' => $tDID, 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', 'app.bsky.graph.getFollowers', $args);
                    $arrFoll = array_merge($arrFoll, (array)$res->followers);
                    $cursor = $res->cursor ?? '';
                } while ($cursor);

                // Fetch follows
                $cursor = '';
                do {
                    $args = ['actor' => $tDID, 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', 'app.bsky.graph.getFollows', $args);
                    $arrFollowings = array_merge($arrFollowings, (array)$res->follows);
                    $cursor = $res->cursor ?? '';
                } while ($cursor);

                // Filter based on selection
                switch ($filterType) {
                    case 'not_followed_by_me':
                        $arrFollowings = array_diff(array_map(fn($f) => $f->did, $arrFollowings), array_map(fn($f) => $f->did, $arrFoll));
                        break;
                    case 'followed_by_me':
                        $arrFollowings = array_intersect(array_map(fn($f) => $f->did, $arrFollowings), array_map(fn($f) => $f->did, $arrFoll));
                        break;
                    case 'follows_me':
                        $arrFoll = array_intersect(array_map(fn($f) => $f->did, $arrFoll), array_map(fn($f) => $f->did, $arrFollowings));
                        break;
                    case 'not_follows_me':
                        $arrFoll = array_diff(array_map(fn($f) => $f->did, $arrFoll), array_map(fn($f) => $f->did, $arrFollowings));
                        break;
                    case 'all':
                    default:
                        // No filtering needed
                        break;
                }

                $listItems = $listType === 'followers' ? $arrFoll : $arrFollowings;

                // Create a new list
                $listName = ($listType === 'followers' ? 'Followers' : 'Follows') . ' ' . date('Y-m-d');
                $args = [
                    'collection' => 'app.bsky.graph.list',
                    'repo' => $bluesky->getAccountDid(),
                    'record' => [
                        'createdAt' => date('c'),
                        '$type' => 'app.bsky.graph.list',
                        'purpose' => 'app.bsky.graph.defs#curatelist',
                        'name' => $listName,
                        'description' => "List of $listName.",
                    ],
                ];
                if ($data2 = $bluesky->request('POST', 'com.atproto.repo.createRecord', $args)) {
                    $newListURI = $data2->uri;
                    foreach ($listItems as $did) {
                        $args = [
                            'collection' => 'app.bsky.graph.listitem',
                            'repo' => $bluesky->getAccountDid(),
                            'record' => [
                                'subject' => $did,
                                'createdAt' => date('c'),
                                '$type' => 'app.bsky.graph.listitem',
                                'list' => $newListURI
                            ],
                        ];
                        $res = $bluesky->request('POST', 'com.atproto.repo.createRecord', $args);
                    }
                }
            }

            $bluesky = null;
            echo "Import Complete";
        } else {
            echo "Error connecting to your account. Please check the username and app password and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create a list of followers or follows based on relationship</title>
</head>
<body>
    <h1>Create a list of followers or follows based on relationship</h1>
    <?php
    require "./app-pw.php";
    ?>
    <form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <p>Target User Handle: <input type="text" name="target_handle" placeholder="target.bsky.social" required></p>
        <p>
            <input type="radio" name="listType" value="followers" checked> Followers
            <input type="radio" name="listType" value="follows"> Follows
        </p>
        <p>Filter Type:</p>
        <p>
            <input type="radio" name="filterType" value="not_followed_by_me" checked> Users I don't follow
            <input type="radio" name="filterType" value="followed_by_me"> Users I follow
            <input type="radio" name="filterType" value="follows_me"> Users that follow me
            <input type="radio" name="filterType" value="not_follows_me"> Users that don't follow me
            <input type="radio" name="filterType" value="all"> All Users
        </p>
        <input type="submit" name="submit" value="Submit">
    </form>
    <?php
    include "./footer.php";
    ?>
</body>
</html>
