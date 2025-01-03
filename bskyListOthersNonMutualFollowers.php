<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');

if (isset($_POST['submit'])) {
    $BSKY_HANDLETEST = str_replace(["@", 'bsky.app'], ["", 'bsky.social'], $_POST['handle']);
    $BSKY_PWTEST = $_POST['apppassword'];
    $TARGET_HANDLE = str_replace(["@", 'bsky.app'], ["", 'bsky.social'], $_POST['target_handle']);

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
                // Get all the followers for the target user
                $tDID = $tUsr->did;
                $cursor = '';
                $arrFoll = [];
                do {
                    $args = ['actor' => $tDID, 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', 'app.bsky.graph.getFollowers', $args);
                    $arrFoll = array_merge($arrFoll, (array)$res->followers);
                    $cursor = $res->cursor ?? '';
                } while ($cursor);

                // Get all the accounts you follow
                $arrFollowings = [];
                $cursor = '';
                do {
                    $args = ['actor' => $bluesky->getAccountDid(), 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', 'app.bsky.graph.getFollows', $args);
                    $arrFollowings = array_merge($arrFollowings, (array)$res->follows);
                    $cursor = $res->cursor ?? '';
                } while ($cursor);

                // Find non-mutual followers
                $nonMutualFollowers = array_diff(array_map(fn($f) => $f->did, $arrFoll), array_map(fn($f) => $f->did, $arrFollowings));

                // Create a new list for non-mutual followers
                $args = [
                    'collection' => 'app.bsky.graph.list',
                    'repo' => $bluesky->getAccountDid(),
                    'record' => [
                        'createdAt' => date('c'),
                        '$type' => 'app.bsky.graph.list',
                        'purpose' => 'app.bsky.graph.defs#curatelist',
                        'name' => 'Non-Mutual Followers of ' . $TARGET_HANDLE . ' ' . date('Y-m-d'),
                        'description' => "List of followers of $TARGET_HANDLE that I do not follow back.",
                    ],
                ];
                if ($data2 = $bluesky->request('POST', 'com.atproto.repo.createRecord', $args)) {
                    $newListURI = $data2->uri;
                    // Now loop the collection of non-mutual followers into the new list
                    foreach ($nonMutualFollowers as $did) {
                        // Add to the list
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
        } else {
            echo "Error connecting to your account. Please check the username and app password and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>List Non-Mutual Followers of Another User</title>
</head>
<body>
    <h1>List Non-Mutual Followers of Another User</h1>
    <?php
    require "./app-pw.php";
    ?>
    <form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <p>Target User Handle: <input type="text" name="target_handle" placeholder="target.bsky.social" required></p>
        <input type="submit" name="submit" value="Submit">
    </form>
    <?php
    include "./footer.php";
    ?>
</body>
</html>
