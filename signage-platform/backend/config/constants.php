<?php
const API_VERSION = '1.0.0';

const ROLE_SUPER_ADMIN = 'super_admin';
const ROLE_USER        = 'user';

const PER_PAGE_DEFAULT = 20;
const PER_PAGE_MAX     = 100;

const PAIRING_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // no 0/O/1/I
const PAIRING_LENGTH   = 6;

const DEVICE_COMMANDS = ['restart', 'pause', 'resume', 'sync', 'clear_cache', 'screenshot', 'identify'];

const TRANSITIONS      = ['none', 'fade', 'slide_left', 'slide_right', 'slide_up', 'slide_down', 'zoom', 'flip'];
const ORIENTATIONS     = ['landscape', 'portrait', 'square', 'custom'];
const BACKGROUND_TYPES = ['color', 'gradient', 'image', 'video', 'preset'];

const IMAGE_EXT    = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
const VIDEO_EXT    = ['mp4'];
const DOCUMENT_EXT = ['pdf'];
const IMAGE_MIMES    = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const VIDEO_MIMES    = ['video/mp4'];
const DOCUMENT_MIMES = ['application/pdf'];

const THUMB_WIDTH = 400;
