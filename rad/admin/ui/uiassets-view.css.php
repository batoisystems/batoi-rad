<style>
    .container {
        display: flex;
        justify-content: space-between;
    }
    .file-browser {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    .file-browser-row {
        display: flex;
        flex-direction: column;
        align-items: center;
        border: 1px solid #ccc;
        padding: 16px;
        border-radius: 8px;
    }
    .file-browser-icon {
        font-size: 24px;
        margin-bottom: 8px;
    }
    .file-browser-name a {
        font-size: 14px;
        text-align: center;
        text-decoration: none; /* Remove underline */
    }
    #drop-area {
        border: 2px dashed #ccc;
        border-radius: 20px;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .h-100 {
        height: 100%;
    }

    .hidden {
        display: none;
    }

    .my-form {
        text-align: center;
    }

    .fs-4 {
        font-size: 2rem;
    }
    .upload-label {
        display: inline-block;
        padding: 10px 20px;
        cursor: pointer;
        background-color: #007bff;
        color: white;
        border-radius: 5px;
        margin-top: 10px;
    }

    .upload-label:hover {
        background-color: #0056b3; /* Change color on hover */
    }
    .btn {
        cursor: pointer;
    }
    .file-browser.list-view,
    .table-responsive {
        width: 100%;
        }
        .table {
        width: 100%;
        margin-bottom: 1rem;
        color: #212529;
    }

    .table th,
    .table td {
        padding: 0.75rem;
        vertical-align: top;
        border-top: 1px solid #dee2e6;
    }

    .table thead th {
        vertical-align: bottom;
        border-bottom: 2px solid #dee2e6;
    }

    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(0, 0, 0, 0.05);
    }

    .table-hover tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.075);
    }
    .file-icon {
        font-size: 64px; /* Increase the font size to make the icon larger */
    }
    .file-name {
        font-size: 16px;
        font-weight: bold;
    }
    .rad-stacked-toolbar .btn {
        border-radius: 0;
        padding: 0.55rem 1.25rem;
        font-weight: 600;
    }
    .rad-stacked-toolbar .btn:first-child {
        border-top-left-radius: 999px;
        border-bottom-left-radius: 999px;
    }
    .rad-stacked-toolbar .btn:last-child {
        border-top-right-radius: 999px;
        border-bottom-right-radius: 999px;
        border-left: 0;
    }
    .rad-stacked-toolbar .btn-outline-primary {
        color: #11a98e;
        border-color: #11a98e;
    }
    .rad-stacked-toolbar .btn-outline-primary:hover,
    .rad-stacked-toolbar .btn-outline-secondary:hover {
        background-color: #11a98e;
        color: #fff;
    }
    .rad-stacked-toolbar .btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
    .rad-stacked-toolbar .btn-primary:hover {
        opacity: 0.9;
    }
</style>
