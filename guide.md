# JAVTEEN implementation guide

## Architecture
- `javteen-theme/` provides branding, responsive templates, search, archives, cards, and player rendering.
- `javteen-core/` registers the Video post type, categories, and Playback Type / Video URL fields.

## Install
1. Upload and activate JAVTEEN Core as a plugin.
2. Upload and activate JAVTEEN under Appearance → Themes.
3. Go to Videos → Add New; add title, description, featured image, and playback URL.
4. Choose direct media URL or a supported WordPress oEmbed provider URL.
5. Test on a staging site and verify permalinks if the archive does not appear.

## Security
Admin saves verify a nonce and `edit_post` capability. URLs are sanitized and restricted to HTTP/HTTPS. Templates escape dynamic output. Embed mode uses WordPress oEmbed rather than accepting arbitrary iframe code. Publish only content you are authorized to distribute and use appropriate age/access controls.

## QA checklist
- [ ] Test direct MP4 and supported oEmbed playback.
- [ ] Test title search, mobile menu, archive pagination, and keyboard navigation.
- [ ] Verify invalid URL schemes are rejected.
- [ ] Test HLS on target browsers; native support varies.
- [ ] Review PHP logs and browser console before production.
