# BeyondMeds updated website

Open index.html directly in a modern browser. No build tools or installation are required.
The separate BeyondMeds-Preview.html embeds its images, CSS, JavaScript and fallback font and works offline.

## Included
- The original emblem and wordmark, extracted as transparent SVG paths from the supplied brand guideline.
- The existing navy #17232D, teal #075156, orange #FF5C03 and ice blue #E5EEEF palette.
- A person wearing the cap in a sticky scroll-animated hero, with scene buttons and a motion pause control.
- Six animated sections: migraine discomfort, tension headaches, sinus pressure, stress, hangovers and puffy eyes.
- Black, blue and pink product selection using the existing colour image. The cap shape and colours have not been regenerated.
- Quantity selection, hot/cold atmosphere controls, accordions and mobile navigation.
- A redesigned orange product CTA. Checkout is unconnected, as it was in the supplied site; no payment or order is collected.
- No progress bars or progress lines.

## Typography limitation — action required
Syne is declared as the primary typeface, but the font download host was blocked during this session and no Syne font file was present locally.
The offline preview therefore uses the bundled PP Mori fallback unless Syne is installed on the viewing computer.
This is NOT a completed Syne implementation.
To finish: supply the licensed Syne variable WOFF2 as assets/syne.woff2. Change the first @font-face source in styles.css from local("Syne") to url("assets/syne.woff2") format("woff2").
Run node build-preview.mjs to regenerate both deliverables with the font embedded.

## Connect purchases
In config.js set checkoutUrls.black, checkoutUrls.blue and checkoutUrls.pink to the actual variant purchase URLs.
The chosen quantity is added as the quantity URL parameter. Verify the target store's parameter convention before launch.
shopUrl is an optional general fallback. Empty values open the preview dialog with selected colour and quantity.

## Source assets
person.png: existing local "Peaceful Rest with a Migraine Mask.png".
colors.png: existing local Beyondmeds.pk/Assets/colors.png, displayed through CSS crops.
material.png: existing local BeyondMeds/blog-2.png.
logo.svg: vector extraction of the original emblem and wordmark from "Beyond meds brand Guideline Revised.pdf", page 3.
fallback.woff2: existing project's PPMori-Regular.woff2.
No external image dependencies are used in this version.

## Source structure
index.html — complete updated page
styles.css — layout, motion, responsive styles
app.js — interactions
config.js — purchase destinations
assets/ — images, vector logo and fallback font
build-preview.mjs — dependency-free Node script rebuilding the preview and ZIP

The earlier experimental pages are kept in the untouched original extracted folder, not served as part of this updated site.
