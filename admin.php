<?php
require_once 'config.php';
requireAdmin();

$csrf  = csrf();
$myId  = uid();

// ── HANDLE ADMIN ACTIONS ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Verify artisan
    if ($action === 'verify_artisan') {
        $pid = (int)($_POST['profile_id'] ?? 0);
        db()->prepare("UPDATE artisan_profiles SET verification_status='verified' WHERE profile_id=?")->execute([$pid]);
        flash('success', 'Artisan verified successfully.');
        redirect('admin.php?t=artisans');
    }

    // Unverify artisan
    if ($action === 'unverify_artisan') {
        $pid = (int)($_POST['profile_id'] ?? 0);
        db()->prepare("UPDATE artisan_profiles SET verification_status='unverified' WHERE profile_id=?")->execute([$pid]);
        flash('success', 'Artisan verification revoked.');
        redirect('admin.php?t=artisans');
    }

    // Delete user
    if ($action === 'delete_user') {
        $uid2 = (int)($_POST['user_id'] ?? 0);
        if ($uid2 === $myId) { flash('error', 'You cannot delete your own account.'); redirect('admin.php?t=users'); }
        db()->prepare("DELETE FROM users WHERE user_id=?")->execute([$uid2]);
        flash('success', 'User deleted.');
        redirect('admin.php?t=users');
    }

    // Toggle availability
    if ($action === 'toggle_available') {
        $pid = (int)($_POST['profile_id']??0);
        db()->prepare("UPDATE artisan_profiles SET is_available = NOT is_available WHERE profile_id=?")->execute([$pid]);
        flash('success','Availability toggled.'); redirect('admin.php?t=artisans');
    }
}

// ── STATS ────────────────────────────────────────────────────
$stats = [
    'total_users'     => db()->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_artisans'  => db()->query("SELECT COUNT(*) FROM artisan_profiles")->fetchColumn(),
    'verified'        => db()->query("SELECT COUNT(*) FROM artisan_profiles WHERE verification_status='verified'")->fetchColumn(),
    'customers'       => db()->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
    'total_bookings'  => db()->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'completed'       => db()->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn(),
    'pending'         => db()->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(),
    'total_reviews'   => db()->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    'avg_rating'      => db()->query("SELECT AVG(overall_rating) FROM reviews")->fetchColumn(),
    'total_messages'  => db()->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
];

// ── FETCH DATA BASED ON TAB ───────────────────────────────
$tab  = $_GET['t'] ?? 'overview';
$search = trim($_GET['q'] ?? '');

// Artisans list
$aFilter = $search ? "AND (u.first_name LIKE ? OR u.last_name LIKE ? OR a.trade_category LIKE ?)" : '';
$aParams = $search ? ["%$search%","%$search%","%$search%"] : [];
$artisansStmt = db()->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email, u.phone, u.created_at AS joined, u.user_id AS uid
    FROM artisan_profiles a JOIN users u ON a.user_id=u.user_id
    WHERE 1=1 $aFilter
    ORDER BY a.created_at DESC LIMIT 100
");
$artisansStmt->execute($aParams);
$artisans = $artisansStmt->fetchAll();

// Users list
$uFilter = $search ? "AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)" : '';
$uParams = $search ? ["%$search%","%$search%","%$search%"] : [];
$usersStmt = db()->prepare("SELECT * FROM users WHERE 1=1 $uFilter ORDER BY created_at DESC LIMIT 100");
$usersStmt->execute($uParams);
$users = $usersStmt->fetchAll();

// Recent bookings
$bookings = db()->query("
    SELECT b.*, u.first_name AS cust_first, u.last_name AS cust_last,
           u2.first_name AS art_first, u2.last_name AS art_last, ap.trade_category
    FROM bookings b
    JOIN users u ON b.customer_user_id=u.user_id
    JOIN artisan_profiles ap ON b.artisan_id=ap.profile_id
    JOIN users u2 ON ap.user_id=u2.user_id
    ORDER BY b.created_at DESC LIMIT 50
")->fetchAll();

// Recent reviews
$reviews = db()->query("
    SELECT r.*, u.first_name AS cust_first, u.last_name AS cust_last,
           u2.first_name AS art_first, u2.last_name AS art_last
    FROM reviews r
    JOIN users u ON r.customer_user_id=u.user_id
    JOIN artisan_profiles ap ON r.artisan_id=ap.profile_id
    JOIN users u2 ON ap.user_id=u2.user_id
    ORDER BY r.created_at DESC LIMIT 30
")->fetchAll();

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

 <!-- TABS -->
 <div class="tabs">
  <button class="tab <?=$tab==='overview'?'on':''?>" onclick="sw('overview')">📊 Overview</button>
  <button class="tab <?=$tab==='artisans'?'on':''?>" onclick="sw('artisans')">🔨 Artisans <span style="background:var(--green);color:#fff;border-radius:20px;padding:.05rem .4rem;font-size:.7rem"><?=count($artisans)?></span></button>
  <button class="tab <?=$tab==='users'?'on':''?>" onclick="sw('users')">👥 Users <span style="background:var(--green);color:#fff;border-radius:20px;padding:.05rem .4rem;font-size:.7rem"><?=count($users)?></span></button>
  <button class="tab <?=$tab==='bookings'?'on':''?>" onclick="sw('bookings')">📋 Bookings</button>
  <button class="tab <?=$tab==='reviews'?'on':''?>" onclick="sw('reviews')">⭐ Reviews</button>
 </div>

 <!-- ── OVERVIEW ──────────────────────────────────────────── -->
 <div id="tp-overview" class="tp <?=$tab==='overview'?'on':''?>">
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
    $recent = db()->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll();
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

 <!-- ── ARTISANS ───────────────────────────────────────────── -->
 <div id="tp-artisans" class="tp <?=$tab==='artisans'?'on':''?>">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.7rem;margin-bottom:1rem">
   <h3 style="font-weight:700">Registered Artisans (<?=count($artisans)?>)</h3>
   <form method="get" style="display:flex;gap:.5rem">
    <input type="hidden" name="t" value="artisans">
    <input name="q" class="fc" placeholder="Search artisans…" value="<?=e($search)?>" style="width:220px">
    <button class="btn btn-p btn-sm" type="submit">Search</button>
    <?php if($search): ?><a href="admin.php?t=artisans" class="btn btn-o btn-sm">Clear</a><?php endif ?>
   </form>
  </div>
  <div class="tw">
  <table>
   <thead><tr><th>Name</th><th>Trade</th><th>Location</th><th>Rating</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
   <tbody>
   <?php foreach($artisans as $a): ?>
   <tr>
    <td>
     <a href="profile.php?id=<?=$a['profile_id']?>" target="_blank" style="font-weight:600"><?=e($a['first_name'].' '.$a['last_name'])?></a>
     <div style="font-size:.78rem;color:var(--light)"><?=e($a['email'])?></div>
    </td>
    <td><?=e($a['trade_category'])?><br><small style="color:var(--light)"><?=e($a['trade_subcategory']??'')?></small></td>
    <td style="font-size:.85rem"><?=e($a['lga_of_operation']??'')?><?=$a['lga_of_operation']?', ':''?><?=e($a['state_of_operation']??'')?></td>
    <td><?=stars((float)$a['average_rating'],false)?> <span style="font-size:.78rem;color:var(--light)">(<?=$a['total_reviews']?>)</span></td>
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
   <?php if(!$artisans): ?><tr><td colspan="7" style="text-align:center;color:var(--light);padding:2rem">No artisans found</td></tr><?php endif ?>
   </tbody>
  </table>
  </div>
 </div>

 <!-- ── USERS ─────────────────────────────────────────────── -->
 <div id="tp-users" class="tp <?=$tab==='users'?'on':''?>">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.7rem;margin-bottom:1rem">
   <h3 style="font-weight:700">All Users (<?=count($users)?>)</h3>
   <form method="get" style="display:flex;gap:.5rem">
    <input type="hidden" name="t" value="users">
    <input name="q" class="fc" placeholder="Search users…" value="<?=e($search)?>" style="width:220px">
    <button class="btn btn-p btn-sm" type="submit">Search</button>
    <?php if($search): ?><a href="admin.php?t=users" class="btn btn-o btn-sm">Clear</a><?php endif ?>
   </form>
  </div>
  <div class="tw">
  <table>
   <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
   <tbody>
   <?php foreach($users as $u): ?>
   <tr>
    <td><?=e($u['first_name'].' '.$u['last_name'])?></td>
    <td style="font-size:.85rem"><?=e($u['email'])?></td>
    <td style="font-size:.85rem"><?=e($u['phone']??'—')?></td>
    <td><span class="b <?=$u['role']==='artisan'?'b-g':($u['role']==='admin'?'b-bl':'b-gr')?>"><?=ucfirst($u['role'])?></span></td>
    <td style="font-size:.8rem;color:var(--light)"><?=date('d M Y',strtotime($u['created_at']))?></td>
    <td>
     <?php if($u['user_id']!==$myId): ?>
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
   </tbody>
  </table>
  </div>
 </div>

 <!-- ── BOOKINGS ───────────────────────────────────────────── -->
 <div id="tp-bookings" class="tp <?=$tab==='bookings'?'on':''?>">
  <h3 style="font-weight:700;margin-bottom:1rem">All Bookings (<?=count($bookings)?>)</h3>
  <div class="tw">
  <table>
   <thead><tr><th>Customer</th><th>Artisan</th><th>Trade</th><th>Description</th><th>Price</th><th>Status</th><th>Date</th></tr></thead>
   <tbody>
   <?php foreach($bookings as $bk): ?>
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
   <?php if(!$bookings): ?><tr><td colspan="7" style="text-align:center;color:var(--light);padding:2rem">No bookings yet</td></tr><?php endif ?>
   </tbody>
  </table>
  </div>
 </div>

 <!-- ── REVIEWS ────────────────────────────────────────────── -->
 <div id="tp-reviews" class="tp <?=$tab==='reviews'?'on':''?>">
  <h3 style="font-weight:700;margin-bottom:1rem">All Reviews (<?=count($reviews)?>)</h3>
  <div class="tw">
  <table>
   <thead><tr><th>Customer</th><th>Artisan</th><th>Rating</th><th>Title</th><th>Date</th></tr></thead>
   <tbody>
   <?php foreach($reviews as $rv): ?>
   <tr>
    <td><?=e($rv['cust_first'].' '.$rv['cust_last'])?></td>
    <td><?=e($rv['art_first'].' '.$rv['art_last'])?></td>
    <td><?=stars((float)$rv['overall_rating'],false)?></td>
    <td style="font-size:.85rem"><?=e($rv['review_title']?:'—')?></td>
    <td style="font-size:.78rem;color:var(--light)"><?=date('d M Y',strtotime($rv['created_at']))?></td>
   </tr>
   <?php endforeach ?>
   <?php if(!$reviews): ?><tr><td colspan="5" style="text-align:center;color:var(--light);padding:2rem">No reviews yet</td></tr><?php endif ?>
   </tbody>
  </table>
  </div>
 </div>

</div>

<script>
function sw(t){
  document.querySelectorAll('.tp,.tab').forEach(el=>el.classList.remove('on'));
  document.getElementById('tp-'+t).classList.add('on');
  document.querySelectorAll('.tab').forEach(el=>{if(el.textContent.toLowerCase().includes(t.substring(0,4)))el.classList.add('on');});
}
(function(){const p=new URLSearchParams(location.search);const t=p.get('t');if(t)sw(t);})();
</script>

<?php foot(); ?>
