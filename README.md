# BeyondMeds — October 26 update

Open index.html in a modern browser. All website dependencies are included locally.
Run `node build-preview.mjs` to generate the portable preview and website ZIP in the parent directory.

## Hero playback
The supplied October 26 video is 3840 × 2160 at 24 fps (144 frames). Its checkerboard was baked into the video; the frame assets have an alpha mask applied. The native-resolution subject crops are preserved in assets/hero-oct26 (2560 × 2160). Optimized playback assets are in assets/hero-playback (1280 × 1080).

The hero preloads and decodes every playback frame before dismissing the loader. A progress label shows readiness; a failed load offers Retry rather than silently dismissing the loader. Four desktop / two mobile decode workers limit transient memory. Persistent ImageBitmaps use a screen- and memory-budgeted resolution (384 MiB desktop / 180 MiB mobile maximum frame-buffer budget). No frame files are loaded or evicted during scrolling. Adjacent frames blend at fractional scroll positions; this is not AI-generated motion interpolation. The source motion remains 24 fps.

Lenis and hero painting share one animation loop. Painting stops when the scroll position settles; the loop pauses while the tab is hidden. Off-screen section animations pause. Reduced motion disables smooth scrolling and decorative motion.

## Purchase flow
The product colour selector has been removed. The CTA is for the black cap, with quantity controls. The product image has a subtle CSS scroll animation in supporting browsers and remains static elsewhere. The checkout dialog shows the selected quantity and supports keyboard dismissal and focus restoration.

No store/payment service is configured. To enable real checkout, set checkoutUrls.black or shopUrl in config.js. Existing quantity forwarding is retained. The preview never collects payment or claims an order was placed.

## Other details
The supplied header logo, browser title, sample-testimonial disclosure, and existing fonts remain. The footer agency logo is 64 px wide on desktop and 54 px on mobile. Mobile footer content uses a compact two-column layout with credits on a separate row.

Startup optimization: the Desktop preview now references the adjacent BeyondMeds folder; keep them together, or use index.html from the extracted ZIP. Hero startup waits only for its first full-quality frame, then loads coarse fallback frames and nearby frames on demand. Decoded cache is bounded to 36 frames on mobile/modest devices and 48 otherwise. Original production frame resolution and video assets are preserved. Reduced-motion mode uses a still hero. The foliage responds to cursor and scroll, with reduced-motion support.

