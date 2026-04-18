<?php
require_once 'config.php';

$states = ['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno',
           'Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','FCT','Gombe','Imo',
           'Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa',
           'Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba',
           'Yobe','Zamfara'];

$categories = ['Carpentry','Tailoring','Electrical','Plumbing','Painting','Welding',
                'Masonry','Hairdressing','Barbering','Cobbling','Weaving','Pottery',
                'Blacksmithing','Auto Mechanic','Tiling','Upholstery','Photography','Other'];

// ── HANDLE POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── LOGOUT ──────────────────────────────────────────────
    if ($action === 'logout') {
        verifyCsrf();
        session_destroy();
        redirect('auth.php?msg=logged_out');
    }

    // ── LOGIN ────────────────────────────────────────────────
    if ($action === 'login') {
        verifyCsrf();
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        if (!$email || !$pass) { flash('error','Email and password are required.'); redirect('auth.php'); }
        $stmt = db()->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($pass, $user['password_hash'])) {
            flash('error','Invalid email or password.'); redirect('auth.php');
        }
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['user_id'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['email']      = $user['email'];
        $next = $_GET['next'] ?? '';
        flash('success', 'Welcome back, ' . $user['first_name'] . '!');
        redirect($next ?: ($user['role']==='admin'?'admin.php':'dashboard.php'));
    }

    // ── REGISTER ─────────────────────────────────────────────
    if ($action === 'register') {
        verifyCsrf();
        $first   = trim($_POST['first_name'] ?? '');
        $last    = trim($_POST['last_name'] ?? '');
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $phone   = trim($_POST['phone'] ?? '');
        $role    = in_array($_POST['role']??'',['artisan','customer']) ? $_POST['role'] : 'customer';
        $pass    = $_POST['password'] ?? '';
        $pass2   = $_POST['password2'] ?? '';
        $trade   = trim($_POST['trade_category'] ?? '');
        $state   = trim($_POST['state_of_operation'] ?? '');
        $lga     = trim($_POST['lga_of_operation'] ?? '');
        $bio     = trim($_POST['bio'] ?? '');
        $yrs     = (int)($_POST['years_experience'] ?? 0);

        // Validate
        $errors = [];
        if (!$first||!$last)   $errors[] = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($pass !== $pass2)  $errors[] = 'Passwords do not match.';
        if ($role==='artisan' && !$trade) $errors[] = 'Trade category is required for artisans.';

        if (!$errors) {
            // Check duplicate email
            $dup = db()->prepare("SELECT user_id FROM users WHERE email=? LIMIT 1");
            $dup->execute([$email]);
            if ($dup->fetch()) $errors[] = 'An account with this email already exists.';
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('auth.php?tab=register');
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        try {
            db()->beginTransaction();
            $ins = db()->prepare("INSERT INTO users (email,password_hash,role,first_name,last_name,phone,is_verified) VALUES (?,?,?,?,?,?,1)");
            $ins->execute([$email,$hash,$role,$first,$last,$phone]);
            $userId = (int)db()->lastInsertId();

            if ($role === 'artisan') {
                $ia = db()->prepare("INSERT INTO artisan_profiles (user_id,trade_category,state_of_operation,lga_of_operation,bio,years_experience,verification_status) VALUES (?,?,?,?,?,?,'unverified')");
                $ia->execute([$userId,$trade,$state,$lga,$bio,$yrs]);
            }
            db()->commit();
        } catch (PDOException $ex) {
            db()->rollBack();
            flash('error','Registration failed: '.$ex->getMessage());
            redirect('auth.php?tab=register');
        }

        session_regenerate_id(true);
        $_SESSION['user_id']    = $userId;
        $_SESSION['role']       = $role;
        $_SESSION['first_name'] = $first;
        $_SESSION['email']      = $email;
        flash('success','Welcome to ArtisanConnect NG, '.$first.'! '.(
            $role==='artisan'?' Complete your profile to attract customers.':'Start discovering skilled artisans near you.'
        ));
        redirect('dashboard.php');
    }
}

$tab = $_GET['tab'] ?? 'login';
if ($_GET['msg']??''==='logged_out') flash('info','You have been logged out.');

head('Login / Register', '<style>.auth-wrap{max-width:480px;margin:3rem auto;padding:0 1rem}</style>');
?>

<section style="background:var(--green);padding:2rem 1rem;text-align:center;color:#fff">
 <h1 style="font-size:1.8rem;font-weight:800;margin-bottom:.2rem">🔨 ArtisanConnect NG</h1>
 <p style="opacity:.85">Nigeria's premier artisan marketplace</p>
</section>

<div class="auth-wrap">
 <div class="card" style="margin-top:2rem">
  <!-- TABS -->
  <div class="tabs">
   <button class="tab <?=$tab==='login'?'on':''?>" onclick="switchTab('login')">🔑 Login</button>
   <button class="tab <?=$tab==='register'?'on':''?>" onclick="switchTab('register')">📝 Register</button>
  </div>

  <!-- LOGIN TAB -->
  <div id="tp-login" class="tp <?=$tab==='login'?'on':''?>">
   <form method="post" action="auth.php">
    <input type="hidden" name="_csrf" value="<?=csrf()?>">
    <input type="hidden" name="action" value="login">
    <div class="fg"><label>Email Address</label><input name="email" type="email" class="fc" placeholder="yourname@email.com" required autocomplete="email"></div>
    <div class="fg"><label>Password</label><input name="password" type="password" class="fc" placeholder="••••••••" required autocomplete="current-password"></div>
    <button class="btn btn-p btn-blk" type="submit">Login →</button>
    <p style="text-align:center;margin-top:.9rem;font-size:.86rem;color:var(--light)">Don't have an account? <a href="#" onclick="switchTab('register')">Register free</a></p>
   </form>
  </div>

  <!-- REGISTER TAB -->
  <div id="tp-register" class="tp <?=$tab==='register'?'on':''?>">
   <form method="post" action="auth.php" id="regForm">
    <input type="hidden" name="_csrf" value="<?=csrf()?>">
    <input type="hidden" name="action" value="register">

    <div class="fr">
     <div class="fg"><label>First Name *</label><input name="first_name" class="fc" required placeholder="e.g. Chukwu"></div>
     <div class="fg"><label>Last Name *</label><input name="last_name" class="fc" required placeholder="e.g. Emeka"></div>
    </div>
    <div class="fg"><label>Email Address *</label><input name="email" type="email" class="fc" required placeholder="you@email.com"></div>
    <div class="fg"><label>Phone Number</label><input name="phone" type="tel" class="fc" placeholder="0801 234 5678"></div>
    <div class="fg"><label>Password * <small style="font-weight:400;color:var(--light)">(min 6 characters)</small></label><input name="password" type="password" class="fc" required placeholder="••••••••" minlength="6"></div>
    <div class="fg"><label>Confirm Password *</label><input name="password2" type="password" class="fc" required placeholder="••••••••"></div>

    <!-- ROLE -->
    <div class="fg">
     <label>I am registering as *</label>
     <div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin-top:.4rem">
      <label style="border:2px solid var(--border);border-radius:8px;padding:.8rem;cursor:pointer;display:flex;align-items:center;gap:.5rem;font-weight:500;transition:.2s" id="lbl-customer">
       <input type="radio" name="role" value="customer" checked onchange="toggleArtisanFields(false)"> 🛒 Customer
      </label>
      <label style="border:2px solid var(--border);border-radius:8px;padding:.8rem;cursor:pointer;display:flex;align-items:center;gap:.5rem;font-weight:500;transition:.2s" id="lbl-artisan">
       <input type="radio" name="role" value="artisan" onchange="toggleArtisanFields(true)"> 🔨 Artisan
      </label>
     </div>
    </div>

    <!-- ARTISAN-ONLY FIELDS -->
    <div id="artisan-fields" style="display:none;border-top:1px solid var(--border);padding-top:1rem;margin-top:.3rem">
     <p style="font-size:.82rem;color:var(--mid);margin-bottom:.8rem;font-weight:600">📋 ARTISAN PROFILE</p>
     <div class="fg">
      <label>Trade Category *</label>
      <select name="trade_category" class="fc" id="trade_sel">
       <option value="">-- Select your trade --</option>
       <?php foreach($categories as $c): ?><option value="<?=e($c)?>"><?=e($c)?></option><?php endforeach ?>
      </select>
     </div>
     <div class="fr">
      <div class="fg">
       <label>State of Operation</label>
       <select name="state_of_operation" class="fc">
        <option value="">-- Select state --</option>
        <?php foreach($states as $s): ?><option value="<?=e($s)?>"><?=e($s)?></option><?php endforeach ?>
       </select>
      </div>
      <div class="fg">
       <label>LGA</label>
       <input name="lga_of_operation" class="fc" placeholder="e.g. Keffi">
      </div>
     </div>
     <div class="fg"><label>Years of Experience</label><input name="years_experience" type="number" min="0" max="60" class="fc" placeholder="0"></div>
     <div class="fg"><label>Bio / Description <small style="font-weight:400;color:var(--light)">(tell customers about your work)</small></label><textarea name="bio" class="fc" rows="3" placeholder="Describe your skills, specialisations, and work style…"></textarea></div>
    </div>

    <button class="btn btn-p btn-blk" type="submit" style="margin-top:.8rem">Create Account →</button>
    <p style="text-align:center;margin-top:.9rem;font-size:.83rem;color:var(--light)">By registering you agree to our <a href="#">Terms of Service</a></p>
   </form>
  </div>
 </div>
</div>

<script>
function switchTab(t) {
  document.querySelectorAll('.tp').forEach(el => el.classList.remove('on'));
  document.querySelectorAll('.tab').forEach(el => el.classList.remove('on'));
  document.getElementById('tp-'+t).classList.add('on');
  document.querySelectorAll('.tab').forEach(el => { if(el.textContent.toLowerCase().includes(t==='login'?'login':'register')) el.classList.add('on'); });
}
function toggleArtisanFields(show) {
  document.getElementById('artisan-fields').style.display = show ? 'block' : 'none';
  document.getElementById('trade_sel').required = show;
  const lblA = document.getElementById('lbl-artisan');
  const lblC = document.getElementById('lbl-customer');
  if(show) { lblA.style.borderColor='var(--green)';lblA.style.background='var(--green-light)';lblC.style.borderColor='var(--border)';lblC.style.background=''; }
  else { lblC.style.borderColor='var(--green)';lblC.style.background='var(--green-light)';lblA.style.borderColor='var(--border)';lblA.style.background=''; }
}
document.addEventListener('DOMContentLoaded',()=>{ document.getElementById('lbl-customer').style.borderColor='var(--green)'; document.getElementById('lbl-customer').style.background='var(--green-light)'; });
</script>

<?php foot(); ?>
