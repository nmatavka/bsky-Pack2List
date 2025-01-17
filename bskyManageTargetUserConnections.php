<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');
if (isset($_POST['submit'])) {

    $BSKY_HANDLETEST = str_replace(["@", 'bsky.app'], ["", 'bsky.social'], $_POST['handle']);
    $BSKY_PWTEST = $_POST['apppassword'];
    $targetUser = str_replace("@", "", $_POST['targetUser']);
    $actionType = $_POST['actionType']; // follow, block, or add to list
    $userType = $_POST['userType']; // all, following, followers, blocking
    $targetListType = $_POST['targetListType']; // followers or following

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
                'actor' => $targetUser
            ];

            if ($tUsr = $bluesky->request('GET', 'app.bsky.actor.getProfile', $args)) {

                // Get all the accounts the target user follows or followers based on selection
                $tDID = $tUsr->did;
                $cursor = '';
                $arrTargetFollows = [];
                do {
                    $args = ['actor' => $tDID, 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', $targetListType === 'followers' ? 'app.bsky.graph.getFollowers' : 'app.bsky.graph.getFollows', $args);
                    $arrTargetFollows = array_merge($arrTargetFollows, (array)$res->follows);
                    $cursor = $res->cursor ?? '';
                } while ($cursor);

                // Get all the accounts the user follows
                $arrUserFollows = [];
                $cursor = '';
                do {
                    $args = ['actor' => $bluesky->getAccountDid(), 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', 'app.bsky.graph.getFollows', $args);
                    $arrUserFollows = array_merge($arrUserFollows, (array)$res->follows);
                    $cursor = $res->cursor ?? '';
                } while ($cursor);

                // Find follows of the target user that are not followed by the user
                $unfollowedFollows = array_diff(array_map(fn($f) => $f->did, $arrTargetFollows), array_map(fn($f) => $f->did, $arrUserFollows));

                // Filter based on userType
                switch ($userType) {
                    case 'following':
                        $arrUserFollows = array_intersect($arrUserFollows, $unfollowedFollows);
                        break;
                    case 'followers':
                        $arrFoll = array_intersect($arrFoll, $unfollowedFollows);
                        break;
                    case 'blocking':
                        // Assuming you have a way to get blocked users
                        $arrBlocked = []; // Fetch blocked users
                        $arrBlocked = array_intersect($arrBlocked, $unfollowedFollows);
                        break;
                    case 'all':
                    default:
                        // No filtering needed
                        break;
                }

                // Perform action based on actionType
                foreach ($unfollowedFollows as $did) {
                    switch ($actionType) {
                        case 'follow':
                            $args = [
                                'collection' => 'app.bsky.graph.follow',
                                'repo' => $bluesky->getAccountDid(),
                                'record' => [
                                    'subject' => $did,
                                    'createdAt' => date('c'),
                                    '$type' => 'app.bsky.graph.follow',
                                ],
                            ];
                            $res = $bluesky->request('POST', 'com.atproto.repo.createRecord', $args);
                            break;
                        case 'block':
                            $args = [
                                'collection' => 'app.bsky.graph.block',
                                'repo' => $bluesky->getAccountDid(),
                                'record' => [
                                    'subject' => $did,
                                    'createdAt' => date('c'),
                                    '$type' => 'app.bsky.graph.block',
                                ],
                            ];
                            $res = $bluesky->request('POST', 'com.atproto.repo.createRecord', $args);
                            break;
                        case 'add_to_list':
                            // Assuming you have a list URI
                            $listURI = 'your-list-uri';
                            $args = [
                                'collection' => 'app.bsky.graph.listitem',
                                'repo' => $bluesky->getAccountDid(),
                                'record' => [
                                    'subject' => $did,
                                    'createdAt' => date('c'),
                                    '$type' => 'app.bsky.graph.listitem',
                                    'list' => $listURI
                                ],
                            ];
                            $res = $bluesky->request('POST', 'com.atproto.repo.createRecord', $args);
                            break;
                    }
                }
            }

            $bluesky = null;
            echo "Follow Complete";
        } else {
            echo "Error connecting to your account. Please check the username and app password and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Follow unfollowed follows from an account</title>
</head>
<body>
    <h1>Follow unfollowed follows from an account</h1>
    <?php
    require "./app-pw.php";
    ?>
    <form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <p>Target User: <input type="text" name="targetUser" placeholder="username.domain.name" required></p>
        <p>
            <input type="radio" name="targetListType" value="followers" checked> Followers
            <input type="radio" name="targetListType" value="following"> Following
        </p>
        <p>Action Type:</p>
        <p>
            <input type="radio" name="actionType" value="follow" checked> Follow
            <input type="radio" name="actionType" value="block"> Block
            <input type="radio" name="actionType" value="add_to_list"> Add to List
        </p>
        <p>User Type:</p>
        <p>
            <input type="radio" name="userType" value="all" checked> All Users
            <input type="radio" name="userType" value="following"> Users I'm Following
            <input type="radio" name="userType" value="followers"> Users that Follow Me
            <input type="radio" name="userType" value="blocking"> Users I'm Blocking
        </p>
        <input type="submit" name="submit" value="Submit">
    </form>
    <?php
    include "./footer.php";
    ?>
</body>
</html>
