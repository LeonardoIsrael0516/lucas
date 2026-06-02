<?php

/**
 * Biblioteca de mídia unificada (tenant).
 * Limites em kilobytes (regra Laravel `max:` em ficheiros).
 */
return [
    'image_max_kb' => (int) env('MEDIA_LIBRARY_IMAGE_MAX_KB', 10240),
    'pdf_max_kb' => (int) env('MEDIA_LIBRARY_PDF_MAX_KB', 51200),
    'document_max_kb' => (int) env('MEDIA_LIBRARY_DOCUMENT_MAX_KB', 51200),
    'archive_max_kb' => (int) env('MEDIA_LIBRARY_ARCHIVE_MAX_KB', 51200),

    /** MIMEs aceitos no upload direto pelo modal da biblioteca */
    'store_mimetypes' => [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/pjpeg',
        'image/png',
        'image/x-png',
        'image/gif',
        'image/webp',
        'application/zip',
        'application/x-zip-compressed',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
    ],

    /** Extensões aceitas (fallback quando o SO reporta application/octet-stream) */
    'store_extensions' => [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp',
        'zip',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt',
    ],
];
