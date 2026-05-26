<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$vendorData = $this->runData['data']['vendor'] ?? [];
$actionUrl = $radAdminUrl . '/vendor/edit/' . ($vendorData['uid'] ?? '');
$submitLabel = 'Save Changes';
$showHandleInput = false;
include __DIR__ . '/vendor-form.partial.php';
