<?php
$officialWorkspaceCurrent = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$officialWorkspacePanel = strtolower(trim((string)($_GET['panel'] ?? 'seat')));
$officialWorkspaceCan = isset($sbCan) && is_callable($sbCan)
    ? $sbCan
    : static fn (string $permissionKey): bool => true;
?>
<nav class="official-workspace-nav" aria-label="Official management sections">
  <?php if ($officialWorkspaceCan('official_transition')): ?>
    <a
      class="official-workspace-nav__link <?= $officialWorkspaceCurrent === 'OfficialTransitions.php' && $officialWorkspacePanel !== 'access' ? 'active' : '' ?>"
      href="<?= htmlspecialchars(appUrl('Admin-End/OfficialTransitions.php?panel=seat'), ENT_QUOTES, 'UTF-8') ?>"
      <?= $officialWorkspaceCurrent === 'OfficialTransitions.php' && $officialWorkspacePanel !== 'access' ? 'aria-current="page"' : '' ?>>
      <i class="fas fa-chair" aria-hidden="true"></i>
      <span>Seats &amp; Onboarding</span>
    </a>
  <?php endif; ?>
  <?php if ($officialWorkspaceCan('official_records_management')): ?>
    <a
      class="official-workspace-nav__link <?= $officialWorkspaceCurrent === 'OfficialsManagement.php' ? 'active' : '' ?>"
      href="<?= htmlspecialchars(appUrl('Admin-End/OfficialsManagement.php'), ENT_QUOTES, 'UTF-8') ?>"
      <?= $officialWorkspaceCurrent === 'OfficialsManagement.php' ? 'aria-current="page"' : '' ?>>
      <i class="fas fa-address-card" aria-hidden="true"></i>
      <span>Official Records</span>
    </a>
  <?php endif; ?>
  <?php if ($officialWorkspaceCan('official_transition')): ?>
    <a
      class="official-workspace-nav__link <?= $officialWorkspaceCurrent === 'OfficialTransitions.php' && $officialWorkspacePanel === 'access' ? 'active' : '' ?>"
      href="<?= htmlspecialchars(appUrl('Admin-End/OfficialTransitions.php?panel=access'), ENT_QUOTES, 'UTF-8') ?>"
      <?= $officialWorkspaceCurrent === 'OfficialTransitions.php' && $officialWorkspacePanel === 'access' ? 'aria-current="page"' : '' ?>>
      <i class="fas fa-shield-halved" aria-hidden="true"></i>
      <span>Access Templates</span>
    </a>
  <?php endif; ?>
</nav>
