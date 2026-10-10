<?php

namespace App\Support;

class UploadConstraints
{
    public const PDF_MIMES = 'pdf';

    public const DOCUMENT_MIMES = 'pdf,doc,docx';

    public const IMAGE_MIMES = 'jpg,jpeg,png,gif,webp';

    public const PARTNER_IMAGE_MIMES = 'jpg,jpeg,png,gif,svg';

    public const MAX_IMAGE_KB = 5120;

    public const MAX_DOCUMENT_KB = 20480;

    public const SUBMISSIONS_DIR = 'submissions';
}
