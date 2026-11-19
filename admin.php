<?php
require_once 'config.php';
requireAdmin();

$csrf = csrf();
$myId = uid();

$tabs = [
    'overview' => '📊 Overview',
    'artisans' => '🔨 Artisans',
    'users' => '👥 Users',
    'bookings' => '📋 Bookings',
    'reviews' => '⭐ Reviews',
];
$tab = $_GET['t'] ?? 'overview';
if (!is_string($tab) || !isset($tabs[$tab])) {
    $tab = 'overview';
}
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$verificationOptions = [
    '' => 'All verification statuses',
    'needs_verification' => 'Needs verification',
    'pending' => 'Pending',
    'unverified' => 'Unverified',
    'verified' => 'Verified',
];
$verification = $_GET['verification'] ?? '';
if ($tab !== 'artisans' || !is_string($verification) || !isset($verificationOptions[$verification])) {
    $verification = '';
}
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$listQuery = array_filter(['t'=>$tab, 'q'=>$search, 'verification'=>$verification], static fn($value) => $value !== '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $destination = $listQuery + ['page'=>$page];
    $verificationStatuses = ['verify_artisan'=>'verified', 'unverify_artisan'=>'unverified'];
    try {
        if (is_string($action) && isset($verificationStatuses[$action])) {
            $pid = filter_var($_POST['profile_id'] ?? null, FILTER_VALIDATE_INT);
            $status = $verificationStatuses[$action];
            $stmt = db()->prepare("UPDATE artisan_profiles SET verification_status=? WHERE profile_id=? AND verification_status<>?");
            $stmt->execute([$status, $pid ?: 0, $status]);
            if ($stmt->rowCount() === 1) {
                flash('success', $status === 'verified' ? 'Artisan verified.' : 'Artisan verification revoked.');
            } else {
                flash('error', 'No verification change. The artisan is missing or already has that status.');
            }
        } elseif ($action === 'delete_user') {
            $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            if ($userId === $myId) {
                flash('error', 'You cannot delete your own account.');
            } else {
                $stmt = db()->prepare("DELETE FROM users WHERE user_id=?");
                $stmt->execute([$userId ?: 0]);
                flash($stmt->rowCount() === 1 ? 'success' : 'error',
                    $stmt->rowCount() === 1 ? 'User deleted.' : 'User not found. No account was deleted.');
            }
        } elseif ($action === 'toggle_available') {
            $pid = filter_var($_POST['profile_id'] ?? null, FILTER_VALIDATE_INT);
            $stmt = db()->prepare("UPDATE artisan_profiles SET is_available = NOT COALESCE(is_available, 0) WHERE profile_id=?");
            $stmt->execute([$pid ?: 0]);
            flash($stmt->rowCount() === 1 ? 'success' : 'error',
                $stmt->rowCount() === 1 ? 'Availability updated.' : 'Artisan not found. No availability changed.');
        } else {
            flash('error', 'Unknown admin action.');
        }
    } catch (PDOException $error) {
        error_log('Admin action failed: ' . $error->getCode());
        flash('error', 'The action could not be completed. Try again.');
    }
    redirect('admin.php?' . http_build_query($destination));
}

$perPage = 25;
if ($tab === 'overview') {
    $stats = [
        'total_users'    => db()->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_artisans' => db()->query("SELECT COUNT(*) FROM artisan_profiles")->fetchColumn(),
        'verified'       => db()->query("SELECT COUNT(*) FROM artisan_profiles WHERE verification_status='verified'")->fetchColumn(),
        'needs_verification' => db()->query("SELECT COUNT(*) FROM artisan_profiles WHERE verification_status IN ('pending','unverified')")->fetchColumn(),
        'total_bookings' => db()->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
        'completed'      => db()->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn(),
        'pending'        => db()->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(),
        'total_reviews'  => db()->query("SELECT COUNT(*) FROM reviews r JOIN users u ON u.user_id=r.customer_user_id")->fetchColumn(),
        'total_messages' => db()->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
    ];
} else {
    $conditions = [];
    $params = [];
    $needle = '%' . strtr(preg_replace('/\s+/', ' ', $search), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';

    switch ($tab) {
        case 'artisans':
            $from = 'FROM artisan_profiles a JOIN users u ON u.user_id=a.user_id';
            $select = 'a.profile_id, a.trade_category, a.trade_subcategory, a.lga_of_operation,
                a.state_of_operation, a.verification_status, a.is_available,
                u.first_name, u.last_name, u.email, u.created_at AS joined, u.user_id AS uid,
                COALESCE(ratings.average_rating, 0) AS average_rating,
                COALESCE(ratings.total_reviews, 0) AS total_reviews';
            $extraJoin = 'LEFT JOIN (
                SELECT r.artisan_id, COUNT(*) AS total_reviews, AVG(r.overall_rating) AS average_rating
                FROM reviews r JOIN users reviewer ON reviewer.user_id = r.customer_user_id
                GROUP BY r.artisan_id
            ) ratings ON ratings.artisan_id=a.profile_id';
            $order = 'a.created_at DESC, a.profile_id DESC';
            if ($search !== '') {
                $conditions[] = "(CONCAT_WS(' ', u.first_name, u.last_name) LIKE ? ESCAPE '!'
                    OR u.email LIKE ? ESCAPE '!' OR a.trade_category LIKE ? ESCAPE '!'
                    OR a.trade_subcategory LIKE ? ESCAPE '!' OR a.state_of_operation LIKE ? ESCAPE '!'
                    OR a.lga_of_operation LIKE ? ESCAPE '!')";
                $params = array_fill(0, 6, $needle);
            }
            if ($verification === 'needs_verification') {
                $conditions[] = "a.verification_status IN ('pending', 'unverified')";
            } elseif ($verification !== '') {
                $conditions[] = 'a.verification_status=?';
                $params[] = $verification;
            }
            break;
        case 'users':
            $from = 'FROM users';
            $select = 'user_id, first_name, last_name, email, phone, role, created_at';
            $order = 'created_at DESC, user_id DESC';
            if ($search !== '') {
                $conditions[] = "(CONCAT_WS(' ', first_name, last_name) LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!')";
                $params = [$needle, $needle];
            }
            break;
        case 'bookings':
            $from = 'FROM bookings b JOIN users u ON u.user_id=b.customer_user_id
                JOIN artisan_profiles ap ON ap.profile_id=b.artisan_id JOIN users u2 ON u2.user_id=ap.user_id';
            $select = 'b.booking_description, b.agreed_price, b.status, b.created_at, ap.trade_category,
                u.first_name AS cust_first, u.last_name AS cust_last, u2.first_name AS art_first, u2.last_name AS art_last';
            $order = 'b.created_at DESC, b.booking_id DESC';
            break;
        case 'reviews':
            $from = 'FROM reviews r JOIN users u ON u.user_id=r.customer_user_id
                JOIN artisan_profiles ap ON ap.profile_id=r.artisan_id JOIN users u2 ON u2.user_id=ap.user_id';
            $select = 'r.overall_rating, r.review_title, r.created_at,
                u.first_name AS cust_first, u.last_name AS cust_last, u2.first_name AS art_first, u2.last_name AS art_last';
            $order = 'r.created_at DESC, r.review_id DESC';
            break;
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $count = db()->prepare("SELECT COUNT(*) $from $where");
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $join = $extraJoin ?? '';
    $stmt = db()->prepare("SELECT $select $from $join $where ORDER BY $order LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

$statusColors = ['pending'=>'#ed8936','accepted'=>'#48bb78','declined'=>'#e53e3e','in_progress'=>'#4299e1','completed'=>'#38a169','cancelled'=>'#a0aec0'];

head('Admin Dashboard');
?>

<div class="ph">
 <div class="ctr">
  <h1>⚙ Admin Dashboard</h1>
  <p>ArtisanConnect NG Platform Management</p>
 </div>
</div>

<div class="ctr sec" style="padding-top:1.5rem">

 <nav class="tabs" aria-label="Admin sections">
  <?php foreach ($tabs as $key => $label): ?>
  <a href="admin.php?t=<?=e($key)?>" class="tab <?=$tab===$key?'on':''?>"<?=$tab===$key?' aria-current="page"':''?>><?=e($label)?></a>
  <?php endforeach ?>
 </nav>

 <?php if ($tab === 'overview'): ?>
 <div>
  <!-- STAT CARDS -->
  <div class="g4" style="margin-bottom:1.5rem">
   <?php
   $statCards = [
     ['👥','Total Users',$stats['total_users'],'#4299e1'],
     ['🔨','Artisans',$stats['total_artisans'],'#38a169'],
     ['✅','Verified',$stats['verified'],'#48bb78'],
     ['📋','Total Bookings',$stats['total_bookings'],'#ed8936'],
     ['✓','Completed Jobs',$stats['completed'],'#38a169'],
     ['⏳','Pending Bookings',$stats['pending'],'#e53e3e'],
     ['⭐','Reviews',$stats['total_reviews'],'#f5a623'],
     ['💬','Messages',$stats['total_messages'],'#805ad5'],
   ];
   foreach($statCards as [$ico,$lbl,$val,$col]): ?>
   <div class="sc">
    <div class="sc-ico" style="background:<?=$col?>22;color:<?=$col?>"><?=$ico?></div>
    <div><div style="font-size:1.5rem;font-weight:800"><?=number_format((float)$val)?></div><div style="font-size:.8rem;color:var(--light)"><?=$lbl?></div></div>
   </div>
   <?php endforeach ?>
  </div>

  <p style="margin-bottom:1.2rem"><a href="admin.php?t=artisans&amp;verification=needs_verification" class="btn btn-o">Needs verification (<?=$stats['needs_verification']?>)</a></p>

  <!-- QUICK STATS -->
  <div class="g2">
   <div class="card">
    <h3 style="font-weight:700;margin-bottom:1rem">📊 Booking Status Breakdown</h3>
    <?php
    $bkStats = db()->query("SELECT status,COUNT(*) AS cnt FROM bookings GROUP BY status")->fetchAll();
    foreach($bkStats as $bs): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--border)">
     <span style="color:<?=$statusColors[$bs['status']]??'#888'?>;font-weight:600"><?=ucfirst(str_replace('_',' ',$bs['status']))?></span>
     <span style="font-weight:700"><?=$bs['cnt']?></span>
    </div>
    <?php endforeach ?>
   </div>
   <div class="card">
    <h3 style="font-weight:700;margin-bottom:1rem">🔨 Top Trade Categories</h3>
    <?php
    $trades = db()->query("SELECT trade_category,COUNT(*) AS cnt FROM artisan_profiles GROUP BY trade_category ORDER BY cnt DESC LIMIT 8")->fetchAll();
    foreach($trades as $tr): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--border)">
     <span><?=e($tr['trade_category'])?></span>
     <span class="b b-g"><?=$tr['cnt']?></span>
    </div>
    <?php endforeach ?>
   </div>
  </div>

  <!-- RECENT SIGNUPS -->
  <div class="card" style="margin-top:1.2rem">
   <h3 style="font-weight:700;margin-bottom:1rem">🆕 Recent Signups</h3>
   <div class="tw">
   <table>
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
    <tbody>
    <?php
    $recent = db()->query("SELECT first_name, last_name, email, role, created_at FROM users ORDER BY created_at DESC, user_id DESC LIMIT 10")->fetchAll();
    foreach($recent as $u): ?>
    <tr>
     <td><?=e($u['first_name'].' '.$u['last_name'])?></td>
     <td style="font-size:.85rem"><?=e($u['email'])?></td>
     <td><span class="b <?=$u['role']==='artisan'?'b-g':($u['role']==='admin'?'b-bl':'b-gr')?>"><?=e(ucfirst($u['role']))?></span></td>
     <td style="font-size:.82rem;color:var(--light)"><?=ago($u['created_at'])?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
   </table>
   </div>
  </div>
 </div>

 <?php elseif ($tab === 'artisans'): ?>

 <!-- ── ARTISANS ───────────────────────────────────────────── -->
 <div>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.7rem;margin-bottom:1rem">
   <h3 style="font-weight:700">Registered Artisans (<?=$total?>)</h3>
   <form method="get" style="display:flex;flex-wrap:wrap;gap:.5rem">
    <input type="hidden" name="t" value="artisans">
    <input name="q" class="fc" aria-label="Search artisans" placeholder="Search artisans…" value="<?=e($search)?>" style="width:220px">
    <select name="verification" class="fc" aria-label="Verification status" style="width:auto">
     <?php foreach ($verificationOptions as $value => $label): ?>
     <option value="<?=e($value)?>"<?=$verification===$value?' selected':''?>><?=e($label)?></option>
     <?php endforeach ?>
    </select>
    <button class="btn btn-p btn-sm" type="submit">Search</button>
    <?php if($search !== '' || $verification !== ''): ?><a href="admin.php?t=artisans" class="btn btn-o btn-sm">Clear</a><?php endif ?>
   </form>
  </div>
  <div class="tw">
  <table>
   <thead><tr><th>Name</th><th>Trade</th><th>Location</th><th>Rating</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
   <tbody>
   <?php foreach($rows as $a): ?>
   <tr>
    <td>
     <a href="profile.php?id=<?=$a['profile_id']?>" target="_blank" style="font-weight:600"><?=e($a['first_name'].' '.$a['last_name'])?></a>
     <div style="font-size:.78rem;color:var(--light)"><?=e($a['email'])?></div>
    </td>
    <td><?=e($a['trade_category'])?><br><small style="color:var(--light)"><?=e($a['trade_subcategory']??'')?></small></td>
    <td style="font-size:.85rem"><?=e($a['lga_of_operation']??'')?><?=$a['lga_of_operation']?', ':''?><?=e($a['state_of_operation']??'')?></td>
    <td><?php if ((int)$a['total_reviews'] > 0): ?><?=stars((float)$a['average_rating'],false)?> <span style="font-size:.78rem;color:var(--light)">(<?=$a['total_reviews']?>)</span><?php else: ?>No reviews yet<?php endif ?></td>
    <td>
     <span class="b <?=$a['verification_status']==='verified'?'b-g':($a['verification_status']==='pending'?'b-gold':'b-gr')?>"><?=ucfirst($a['verification_status'])?></span>
     <?php if($a['is_available']): ?><br><span class="b b-g" style="font-size:.68rem;margin-top:.2rem">Available</span><?php else: ?><br><span class="b b-r" style="font-size:.68rem;margin-top:.2rem">Unavailable</span><?php endif ?>
    </td>
    <td style="font-size:.8rem;color:var(--light)"><?=date('d M Y',strtotime($a['joined']))?></td>
    <td>
     <?php if($a['verification_status']!=='verified'): ?>
     <form method="post" style="display:inline">
      <input type="hidden" name="_csrf" value="<?=$csrf?>">
      <input type="hidden" name="action" value="verify_artisan">
      <input type="hidden" name="profile_id" value="<?=$a['profile_id']?>">
      <button class="btn btn-p btn-sm" type="submit">✓ Verify</button>
     </form>
     <?php else: ?>
     <form method="post" style="display:inline">
      <input type="hidden" name="_csrf" value="<?=$csrf?>">
      <input type="hidden" name="action" value="unverify_artisan">
      <input type="hidden" name="profile_id" value="<?=$a['profile_id']?>">
      <button class="btn btn-o btn-sm" type="submit">Revoke</button>
     </form>
     <?php endif ?>
     <form method="post" style="display:inline;margin-left:.2rem">
      <input type="hidden" name="_csrf" value="<?=$csrf?>">
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="user_id" value="<?=$a['uid']?>">
      <button class="btn btn-d btn-sm" type="submit" onclick="return confirm('Delete this artisan and ALL their data?')">Del</button>
     </form>
    </td>
   </tr>
   <?php endforeach ?>
   <?php if(!$rows): ?><tr><td colspan="7" style="text-align:center;color:var(--light);padding:2rem">No artisans found</td></tr><?php endif ?>
   </tbody>
  </table>
  </div>
 </div>

 <?php elseif ($tab === 'users'): ?>

 <!-- ── USERS ─────────────────────────────────────────────── -->
 <div>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.7rem;margin-bottom:1rem">
   <h3 style="font-weight:700">All Users (<?=$total?>)</h3>
   <form method="get" style="display:flex;flex-wrap:wrap;gap:.5rem">
    <input type="hidden" name="t" value="users">
    <input name="q" class="fc" aria-label="Search users" placeholder="Search users…" value="<?=e($search)?>" style="width:220px">
    <button class="btn btn-p btn-sm" type="submit">Search</button>
    <?php if($search): ?><a href="admin.php?t=users" class="btn btn-o btn-sm">Clear</a><?php endif ?>
   </form>
  </div>
  <div class="tw">
  <table>
   <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
   <tbody>
   <?php foreach($rows as $u): ?>
   <tr>
    <td><?=e($u['first_name'].' '.$u['last_name'])?></td>
    <td style="font-size:.85rem"><?=e($u['email'])?></td>
    <td style="font-size:.85rem"><?=e($u['phone']??'—')?></td>
    <td><span class="b <?=$u['role']==='artisan'?'b-g':($u['role']==='admin'?'b-bl':'b-gr')?>"><?=ucfirst($u['role'])?></span></td>
    <td style="font-size:.8rem;color:var(--light)"><?=date('d M Y',strtotime($u['created_at']))?></td>
    <td>
     <?php if((int)$u['user_id']!==$myId): ?>
     <form method="post" style="display:inline" onsubmit="return confirm('Permanently delete this user?')">
      <input type="hidden" name="_csrf" value="<?=$csrf?>">
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="user_id" value="<?=$u['user_id']?>">
      <button class="btn btn-d btn-sm" type="submit">Delete</button>
     </form>
     <?php else: ?><span style="font-size:.8rem;color:var(--light)">You</span><?php endif ?>
    </td>
   </tr>
   <?php endforeach ?>
   <?php if (!$rows): ?><tr><td colspan="6" style="text-align:center;padding:2rem">No users found</td></tr><?php endif ?>
   </tbody>
  </table>
  </div>
 </div>

 <?php elseif ($tab === 'bookings'): ?>

 <!-- ── BOOKINGS ───────────────────────────────────────────── -->
 <div>
  <h3 style="font-weight:700;margin-bottom:1rem">All Bookings (<?=$total?>)</h3>
  <div class="tw">
  <table>
   <thead><tr><th>Customer</th><th>Artisan</th><th>Trade</th><th>Description</th><th>Price</th><th>Status</th><th>Date</th></tr></thead>
   <tbody>
   <?php foreach($rows as $bk): ?>
   <tr>
    <td><?=e($bk['cust_first'].' '.$bk['cust_last'])?></td>
    <td><?=e($bk['art_first'].' '.$bk['art_last'])?></td>
    <td style="font-size:.82rem;color:var(--mid)"><?=e($bk['trade_category'])?></td>
    <td style="max-width:180px;font-size:.82rem"><div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px" title="<?=e($bk['booking_description'])?>"><?=e(substr($bk['booking_description'],0,50))?><?=strlen($bk['booking_description'])>50?'…':''?></div></td>
    <td style="font-size:.85rem"><?=$bk['agreed_price']?'₦'.number_format($bk['agreed_price'],0):'—'?></td>
    <td><span class="b" style="background:<?=$statusColors[$bk['status']]??'#ccc'?>22;color:<?=$statusColors[$bk['status']]??'#888'?>;white-space:nowrap"><?=ucfirst(str_replace('_',' ',$bk['status']))?></span></td>
    <td style="font-size:.78rem;color:var(--light);white-space:nowrap"><?=date('d M Y',strtotime($bk['created_at']))?></td>
   </tr>
   <?php endforeach ?>
   <?php if(!$rows): ?><tr><td colspan="7" style="text-align:center;color:var(--light);padding:2rem">No bookings yet</td></tr><?php endif ?>
   </tbody>
  </table>
  </div>
 </div>

 <?php elseif ($tab === 'reviews'): ?>

 <!-- ── REVIEWS ────────────────────────────────────────────── -->
 <div>
  <h3 style="font-weight:700;margin-bottom:1rem">All Reviews (<?=$total?>)</h3>
  <div class="tw">
  <table>
   <thead><tr><th>Customer</th><th>Artisan</th><th>Rating</th><th>Title</th><th>Date</th></tr></thead>
   <tbody>
   <?php foreach($rows as $rv): ?>
   <tr>
    <td><?=e($rv['cust_first'].' '.$rv['cust_last'])?></td>
    <td><?=e($rv['art_first'].' '.$rv['art_last'])?></td>
    <td><?=stars((float)$rv['overall_rating'],false)?></td>
    <td style="font-size:.85rem"><?=e($rv['review_title']?:'—')?></td>
    <td style="font-size:.78rem;color:var(--light)"><?=date('d M Y',strtotime($rv['created_at']))?></td>
   </tr>
   <?php endforeach ?>
   <?php if(!$rows): ?><tr><td colspan="5" style="text-align:center;color:var(--light);padding:2rem">No reviews yet</td></tr><?php endif ?>
   </tbody>
  </table>
  </div>
 </div>
 <?php endif ?>

 <?php if ($tab !== 'overview' && $totalPages > 1): ?>
 <nav aria-label="Pagination" style="display:flex;align-items:center;justify-content:center;gap:.8rem;margin-top:1rem">
  <?php if ($page > 1): ?>
   <a class="btn btn-o btn-sm" href="admin.php?<?=e(http_build_query($listQuery + ['page'=>$page-1]))?>">Previous</a>
  <?php endif ?>
  <span>Page <?=$page?> of <?=$totalPages?></span>
  <?php if ($page < $totalPages): ?>
   <a class="btn btn-o btn-sm" href="admin.php?<?=e(http_build_query($listQuery + ['page'=>$page+1]))?>">Next</a>
  <?php endif ?>
 </nav>
 <?php endif ?>

</div>

<?php foot(); ?>
