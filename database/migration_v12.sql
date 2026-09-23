-- Migration v12: block-based blog editor. Posts get an ordered JSON list of
-- content blocks (heading, rich text, image, gallery/carousel, video, map,
-- doctor card, doctor comparison table, medicine card, fact box, callout,
-- FAQ, auto table-of-contents, quote) instead of one single rich-text field.
-- `content` is kept for posts written before this and for SEO fallback
-- (excerpt/meta-description auto-generation).
ALTER TABLE blog_posts
    ADD COLUMN blocks   LONGTEXT DEFAULT NULL COMMENT 'JSON array of content blocks — when set, takes over rendering from `content`' AFTER content,
    ADD COLUMN category VARCHAR(100) DEFAULT NULL AFTER blocks;
