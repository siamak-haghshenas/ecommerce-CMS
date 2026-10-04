/**
 * TinyMCE, self-hosted from npm under the GPL (license_key "gpl"): everything is bundled, nothing
 * is loaded from Tiny's cloud. Imported only by the editor component, so it is loaded lazily with
 * the pages that use it.
 */
import tinymce from 'tinymce';
import 'tinymce/icons/default';
import 'tinymce/themes/silver';
import 'tinymce/models/dom';
import 'tinymce/skins/ui/oxide/skin.js';
import 'tinymce/skins/ui/oxide/content.js';
import 'tinymce/skins/ui/oxide-dark/skin.js';
import 'tinymce/skins/ui/oxide-dark/content.js';
import 'tinymce/skins/content/default/content.js';
import 'tinymce/skins/content/dark/content.js';
import 'tinymce/plugins/advlist';
import 'tinymce/plugins/autolink';
import 'tinymce/plugins/code';
import 'tinymce/plugins/directionality';
import 'tinymce/plugins/fullscreen';
import 'tinymce/plugins/link';
import 'tinymce/plugins/lists';
import 'tinymce/plugins/table';
import fontUrl from 'vazirmatn/fonts/webfonts/Vazirmatn[wght].woff2?url';
import { tinymceFa } from './fa';

tinymce.addI18n('fa', tinymceFa);

/** The editor's content area: the panel's font, media kept within the width. */
export const contentStyle = `
@font-face { font-family: Vazirmatn; src: url('${fontUrl}') format('woff2'); font-weight: 100 900; font-display: swap; }
body { font-family: Vazirmatn, system-ui, sans-serif; font-size: 15px; line-height: 1.9; margin: 16px; }
img, video { max-width: 100%; height: auto; border-radius: 8px; }
iframe { max-width: 100%; border: 0; border-radius: 8px; }
table { border-collapse: collapse; }
td, th { border: 1px solid #d0d5dd; padding: 6px 10px; }
`;

export { tinymce };
