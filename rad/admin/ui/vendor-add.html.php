<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$vendorData = $this->runData['data']['vendor_prefill'] ?? [];
$actionUrl = $radAdminUrl . '/vendor/add';
$submitLabel = 'Add Library';
$showHandleInput = true;
include __DIR__ . '/vendor-form.partial.php';
