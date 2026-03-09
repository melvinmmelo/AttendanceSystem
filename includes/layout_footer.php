<?php // includes/layout_footer.php ?>
  </div><!-- /.page-content -->
</main>
</div><!-- /.app -->

<!-- Toast Notification -->
<div id="toast"></div>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirm-modal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <span id="confirm-icon"><i class="bi bi-exclamation-triangle"></i></span>
      <div class="modal-title" id="confirm-title">Confirm Action</div>
    </div>
    <div class="modal-body">
      <p id="confirm-message">Are you sure?</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('confirm-modal')">Cancel</button>
      <button class="btn btn-danger" id="confirm-ok-btn">Confirm</button>
    </div>
  </div>
</div>

<!-- Email Preview Modal (Restored) -->
<div class="modal-overlay" id="email-preview-modal">
  <div class="modal" style="max-width: 800px;">
    <div class="modal-header">
      <div class="modal-title" id="email-preview-title">Email Preview</div>
      <button class="modal-close" onclick="closeModal('email-preview-modal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body" style="padding: 0;">
      <iframe id="email-preview-iframe" style="width: 100%; height: 70vh; border: none;"></iframe>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('email-preview-modal')">Close</button>
    </div>
  </div>
</div>

<script src="assets/js/app.js?v=<?= filemtime(BASE_PATH . '/assets/js/app.js') ?>"></script>
</body>
</html>
