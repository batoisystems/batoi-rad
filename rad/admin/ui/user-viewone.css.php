<style>
.user-detail-card {
    border: 1px solid #eef2f7;
    border-radius: 0.75rem;
}
.user-detail-card .display-6 {
    font-size: 2rem;
}
.copy-uid {
    color: #0d6efd;
}
.copy-uid:hover {
    color: #0a58ca;
}
.user-hero {
    border: 1px solid #eef2f7;
    position: relative;
    overflow: hidden;
}
.user-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #0d6efd 0%, #0dcaf0 50%, #20c997 100%);
}
.user-hero .user-actions .btn {
    min-width: 160px;
}
.user-hero .user-actions .btn-outline-secondary,
.user-hero .user-actions .btn-outline-primary {
    background: #fff;
}
.user-meta .copy-uid {
    border-radius: 999px;
}
.workspace-summary {
    border: 1px solid #eef2f7;
    border-radius: 0.75rem;
}
.user-meta-strip span {
    background: #f8f9fb;
    border-radius: 999px;
    padding: 0.25rem 0.6rem;
}
</style>
