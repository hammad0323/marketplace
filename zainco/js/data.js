/* =====================================================================
   ZAINCO PACKAGING — SITE DATA
   Edit this file to change contact details, stats, categories and the
   product catalogue. Everything on the site is rendered from here.
   ===================================================================== */

const SITE = {
  name: "Zainco Packaging Industries",
  short: "Zainco",
  tagline: "Complete packaging solutions — bags, boxes, cartons & films",
  phone: "0309 4080606",
  phoneIntl: "+923094080606",
  whatsapp: "923094080606",
  email: "sales@zaincopackaging.com", // TODO: replace with the real business email
  address: "Main SITE Industrial Road Karachi Askari 1 apt, Adam Rd, Clifton, Karachi, 79600",
  mapQuery: "Zainco Packaging Industries, Karachi",
  hours: "Mon – Sat · 9:00 AM – 7:00 PM",
  currency: "Rs.",
  // Headline figures shown on the home page — update with the real numbers.
  stats: [
    { value: 15, suffix: "+", label: "Years of experience" },
    { value: 500, suffix: "+", label: "Business clients" },
    { value: 35, suffix: "+", label: "Packaging products" },
    { value: 10, suffix: "M+", label: "Bags & boxes / year" }
  ]
};

// Remote photos (Unsplash). Every place that uses one also has a local
// fallback, so the site still looks complete offline. Swap these for real
// photos of the Zainco factory whenever available (e.g. "img/factory-1.jpg").
const PHOTOS = {
  hero: "https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1920&q=70",
  warehouse: "https://images.unsplash.com/photo-1553413077-190dd305871c?auto=format&fit=crop&w=1920&q=70",
  factory: "https://images.unsplash.com/photo-1565793298595-6a879b1d9492?auto=format&fit=crop&w=1600&q=70",
  engineer: "https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=1200&q=70",
  boxes: "https://images.unsplash.com/photo-1605600659873-d808a13e4d2a?auto=format&fit=crop&w=1600&q=70",
  bags: "https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?auto=format&fit=crop&w=1600&q=70",
  team: "https://images.unsplash.com/photo-1600880292203-757bb62b4baf?auto=format&fit=crop&w=1200&q=70"
};

const CATEGORIES = [
  { id: "pp-woven", name: "PP Woven Bags", icon: "sack", img: "pp-woven-sack-color",
    desc: "Heavy-duty polypropylene woven sacks for grains, sugar, flour, cement, fertilizer and chemicals. Laminated or un-laminated, 5 KG to 100 KG." },
  { id: "bopp", name: "BOPP Laminated Bags", icon: "layers", img: "bopp-rice-bag",
    desc: "Photo-quality, multi-colour printed BOPP laminated woven bags for rice, pet food, animal feed and retail brands." },
  { id: "paper", name: "Paper Bags & Sacks", icon: "bag", img: "kraft-paper-bag",
    desc: "Kraft and white paper carry bags, multi-wall paper sacks and food-grade bakery bags — the eco-friendly choice." },
  { id: "corrugated", name: "Corrugated Boxes", icon: "box", img: "corrugated-box",
    desc: "3-ply, 5-ply and 7-ply corrugated cartons, shipping boxes, e-commerce mailers and die-cut boxes, printed to order." },
  { id: "cartons", name: "Folding Cartons", icon: "carton", img: "pharma-carton",
    desc: "Offset-printed folding cartons for pharmaceutical, FMCG, food and cosmetic brands with foiling, UV and embossing." },
  { id: "flexible", name: "Flexible Packaging", icon: "pouch", img: "standup-pouch",
    desc: "Stand-up pouches, zipper bags, 3-side seal sachets and printed laminated roll stock up to 10 colours." },
  { id: "fibc", name: "FIBC Jumbo Bags", icon: "jumbo", img: "fibc-jumbo-bag",
    desc: "Bulk bags from 500 KG to 2,000 KG — U-panel, circular, baffle and food-grade liners for minerals, grains and chemicals." },
  { id: "poly", name: "Poly & Courier Bags", icon: "mail", img: "courier-mailer",
    desc: "LDPE / HDPE shopping bags, tamper-proof courier mailers, garbage bags, stretch & shrink films." },
  { id: "eco", name: "Eco & Accessories", icon: "leaf", img: "jute-bag",
    desc: "Jute and non-woven bags, packing tape, bubble wrap, labels, food containers and egg trays." }
];

// price = indicative price per unit (Rs.) at the minimum order quantity (moq).
const PRODUCTS = [
  // PP woven
  { id: "pp-woven-sack", cat: "pp-woven", name: "PP Woven Sack — Plain White", price: 28, moq: 1000, unit: "bag", img: "pp-woven-sack", badge: "Best Seller",
    sizes: ["10 KG", "25 KG", "50 KG", "100 KG"], material: "Virgin polypropylene, 60–110 GSM", features: ["Hemmed or open mouth", "UV-stabilised option", "Inner LDPE liner optional", "Strong double-stitched bottom"],
    desc: "Our most popular general-purpose sack for grain, sugar, salt, pulses and seeds. Tough woven fabric with high tensile strength and low elongation." },
  { id: "pp-woven-sack-color", cat: "pp-woven", name: "Coloured PP Woven Bag", price: 32, moq: 1000, unit: "bag", img: "pp-woven-sack-color",
    sizes: ["25 KG", "40 KG", "50 KG"], material: "Pigmented polypropylene, 70–90 GSM", features: ["Any Pantone colour", "1–4 colour flexo print", "Anti-slip coating option", "Custom cut lengths"],
    desc: "Colour-coded woven bags that make your product easy to identify in the warehouse and on the market shelf." },
  { id: "pp-cement-bag", cat: "pp-woven", name: "Cement & Construction Bag", price: 30, moq: 5000, unit: "bag", img: "pp-cement-bag",
    sizes: ["40 KG", "50 KG"], material: "Laminated PP woven, block-bottom valve", features: ["Valve or open mouth", "Micro-perforated for de-aeration", "Moisture-resistant lamination", "High-speed filler compatible"],
    desc: "Block-bottom valve bags engineered for cement, gypsum, putty and tile adhesive with minimal breakage in transit." },
  { id: "pp-flour-bag", cat: "pp-woven", name: "Atta / Flour Bag", price: 22, moq: 2000, unit: "bag", img: "pp-flour-bag", badge: "Food Grade",
    sizes: ["5 KG", "10 KG", "20 KG", "50 KG"], material: "Food-grade PP woven, 55–75 GSM", features: ["Food-safe inks", "Handle cut-out option", "Brand printing up to 4 colours", "Easy-open stitch"],
    desc: "Hygienic, food-grade sacks for atta, maida, sooji and besan — available with carry handles for retail sizes." },
  { id: "paper-sack", cat: "paper", name: "Multi-wall Paper Sack", price: 48, moq: 2000, unit: "sack", img: "paper-sack",
    sizes: ["25 KG", "40 KG", "50 KG"], material: "2–4 ply sack kraft, PE barrier optional", features: ["Pasted valve or open mouth", "Grease and moisture barrier", "Up to 4 colour print", "Recyclable"],
    desc: "Multi-ply kraft paper sacks for chemicals, milk powder, starch, minerals and building materials." },

  // BOPP
  { id: "bopp-rice-bag", cat: "bopp", name: "BOPP Laminated Rice Bag", price: 55, moq: 2000, unit: "bag", img: "bopp-rice-bag", badge: "Export Quality",
    sizes: ["5 KG", "10 KG", "25 KG", "50 KG"], material: "BOPP film laminated on PP woven", features: ["8-colour rotogravure print", "Gloss or matte finish", "Handle & zipper options", "Export grade for basmati"],
    desc: "Eye-catching photographic print quality for basmati and sella rice brands — the standard for Pakistan's rice exporters." },
  { id: "bopp-feed-bag", cat: "bopp", name: "BOPP Animal & Pet Feed Bag", price: 60, moq: 2000, unit: "bag", img: "bopp-feed-bag",
    sizes: ["10 KG", "20 KG", "40 KG"], material: "BOPP / PP woven with PE coating", features: ["Gusseted body", "Grease-proof", "Easy-open pinch bottom option", "Matte or gloss"],
    desc: "Strong, scuff-resistant bags for poultry feed, cattle feed and pet food with premium shelf presence." },

  // Paper
  { id: "kraft-paper-bag", cat: "paper", name: "Kraft Paper Carry Bag", price: 18, moq: 1000, unit: "bag", img: "kraft-paper-bag", badge: "Eco",
    sizes: ["Small 8×10\"", "Medium 10×12\"", "Large 12×16\"", "XL 16×18\""], material: "Brown kraft 80–150 GSM", features: ["Twisted paper handles", "1–2 colour logo print", "Flat or gusseted", "100% recyclable"],
    desc: "Sturdy brown kraft shopping bags for retail stores, bakeries, pharmacies and boutiques." },
  { id: "white-paper-bag", cat: "paper", name: "Premium White Shopping Bag", price: 35, moq: 500, unit: "bag", img: "white-paper-bag",
    sizes: ["Small", "Medium", "Large"], material: "White art card 170–250 GSM", features: ["Rope or ribbon handles", "Lamination & foil stamping", "Reinforced base", "Full-colour offset print"],
    desc: "Luxury branded shopping bags for fashion, jewellery and gifting with a premium unboxing feel." },
  { id: "food-paper-bag", cat: "paper", name: "Food & Bakery Paper Bag", price: 6, moq: 5000, unit: "bag", img: "food-paper-bag",
    sizes: ["Burger", "Fries", "Bread", "Takeaway"], material: "Food-grade MG kraft / greaseproof", features: ["Grease-resistant", "Food-safe inks", "SOS flat-bottom", "Window option"],
    desc: "Greaseproof bags for restaurants, bakeries and fast food — safe, strong and print-ready." },

  // Corrugated
  { id: "corrugated-box", cat: "corrugated", name: "3-Ply Corrugated Carton", price: 45, moq: 500, unit: "box", img: "corrugated-box", badge: "Best Seller",
    sizes: ["10×8×6\"", "12×10×8\"", "16×12×10\"", "Custom"], material: "Kraft liner + B/C flute", features: ["Regular slotted (RSC)", "1–2 colour flexo print", "Bursting strength tested", "Custom die-cuts"],
    desc: "Everyday shipping cartons for retail, garments, electronics and FMCG distribution." },
  { id: "shipping-box-5ply", cat: "corrugated", name: "5-Ply Heavy Duty Export Box", price: 95, moq: 300, unit: "box", img: "shipping-box-5ply",
    sizes: ["18×14×12\"", "20×16×14\"", "24×18×16\"", "Custom"], material: "Double wall BC flute, test liner", features: ["High stacking strength", "Export marking print", "Moisture-resistant coating", "Palletised supply"],
    desc: "Double-wall cartons for exporters of textiles, fruits, ceramics and machinery parts." },
  { id: "printed-ecom-box", cat: "corrugated", name: "Printed E-commerce Mailer Box", price: 70, moq: 300, unit: "box", img: "printed-ecom-box", badge: "Trending",
    sizes: ["Small", "Medium", "Large"], material: "E-flute, white or kraft", features: ["Full-colour inside & out", "Tuck-front, no tape needed", "Tear strip option", "Brand unboxing experience"],
    desc: "Branded mailer boxes for online stores — make every delivery an unboxing moment." },
  { id: "pizza-box", cat: "corrugated", name: "Pizza & Food Delivery Box", price: 25, moq: 1000, unit: "box", img: "pizza-box",
    sizes: ["8\"", "10\"", "12\"", "14\""], material: "Food-grade E/B flute", features: ["Grease-resistant", "Vent holes", "2-colour print", "Flat-packed"],
    desc: "Sturdy, insulated food delivery boxes for pizzerias, bakeries and cloud kitchens." },

  // Cartons
  { id: "pharma-carton", cat: "cartons", name: "Pharmaceutical Folding Carton", price: 4, moq: 10000, unit: "carton", img: "pharma-carton",
    sizes: ["Blister", "Bottle", "Tube", "Custom"], material: "Duplex / SBS board 250–350 GSM", features: ["Braille embossing", "Batch & expiry area", "Tamper-evident flaps", "cGMP-friendly production"],
    desc: "Precision-printed cartons for tablets, syrups and ointments, built to pharma compliance standards." },
  { id: "food-carton", cat: "cartons", name: "FMCG & Food Carton", price: 6, moq: 10000, unit: "carton", img: "food-carton",
    sizes: ["Cereal", "Biscuit", "Tea", "Custom"], material: "Food-grade duplex board", features: ["Window patching", "Aqueous / UV coating", "Auto-lock bottom", "Food-safe inks"],
    desc: "Shelf-ready cartons for biscuits, tea, cereals, spices and confectionery." },
  { id: "cosmetic-carton", cat: "cartons", name: "Cosmetic Luxury Carton", price: 12, moq: 3000, unit: "carton", img: "cosmetic-carton", badge: "Premium",
    sizes: ["Lipstick", "Cream jar", "Perfume", "Custom"], material: "SBS / metallised board", features: ["Hot-foil stamping", "Spot UV & embossing", "Soft-touch lamination", "Magnetic rigid option"],
    desc: "Luxury finishes for cosmetics, perfumes and personal care that elevate brand value." },

  // Flexible
  { id: "standup-pouch", cat: "flexible", name: "Stand-up Pouch with Window", price: 14, moq: 5000, unit: "pouch", img: "standup-pouch", badge: "Trending",
    sizes: ["100 g", "250 g", "500 g", "1 KG"], material: "PET / PE or Kraft / PE laminate", features: ["Clear window", "Resealable zipper", "Tear notch & hang hole", "High barrier option"],
    desc: "Premium stand-up pouches for dry fruits, coffee, snacks, pet treats and spices." },
  { id: "zipper-pouch", cat: "flexible", name: "Zipper Lock Pouch", price: 11, moq: 5000, unit: "pouch", img: "zipper-pouch",
    sizes: ["100 g", "250 g", "500 g"], material: "PET / MET-PET / PE", features: ["Press-to-close zipper", "Metallised barrier", "Matte or gloss", "Up to 10 colours"],
    desc: "Resealable flat and stand-up zipper bags that keep products fresh after opening." },
  { id: "spice-pouch", cat: "flexible", name: "3-Side Seal Spice Sachet", price: 3, moq: 20000, unit: "sachet", img: "spice-pouch",
    sizes: ["10 g", "25 g", "50 g", "100 g"], material: "PET / Foil / PE", features: ["Aroma barrier", "High-speed VFFS ready", "Tear notch", "Rotogravure print"],
    desc: "Sachets and pouches for spices, masalas, shampoos and powders." },
  { id: "laminated-roll", cat: "flexible", name: "Printed Laminated Roll Stock", price: 950, moq: 100, unit: "kg", img: "laminated-roll",
    sizes: ["2-layer", "3-layer", "4-layer"], material: "BOPP / PET / MET / Foil / PE", features: ["Auto-pack machine ready", "10-colour rotogravure", "Solvent-less lamination", "Cold-seal option"],
    desc: "Printed rollstock for chips, biscuits, confectionery, noodles and detergents on form-fill-seal lines." },

  // FIBC
  { id: "fibc-jumbo-bag", cat: "fibc", name: "FIBC Jumbo Bag — 1 Ton", price: 2400, moq: 50, unit: "bag", img: "fibc-jumbo-bag", badge: "Industrial",
    sizes: ["500 KG", "1000 KG", "1500 KG", "2000 KG"], material: "UV-stabilised PP fabric, 160–220 GSM", features: ["4 cross-corner loops", "Spout top & discharge bottom", "5:1 safety factor", "PE liner optional"],
    desc: "Bulk bags for sand, minerals, chemicals, grains and plastic granules with tested safe working loads." },
  { id: "jumbo-bag-baffle", cat: "fibc", name: "Baffle Q-Bag (Food Grade)", price: 3200, moq: 50, unit: "bag", img: "jumbo-bag-baffle",
    sizes: ["1000 KG", "1500 KG"], material: "Food-grade PP fabric with baffles", features: ["Square shape for 30% more storage", "Clean-room production", "Liner with fitted spout", "Sift-proof seams"],
    desc: "Square-shaped baffle bags for sugar, flour, rice and food ingredients — maximise container space." },

  // Poly
  { id: "poly-shopping-bag", cat: "poly", name: "LDPE Shopping Bag", price: 5, moq: 5000, unit: "bag", img: "poly-shopping-bag",
    sizes: ["Small", "Medium", "Large"], material: "LDPE / HDPE 40–80 micron", features: ["D-cut or loop handle", "Up to 4 colour print", "Thicker reusable options", "Compliant micron grades"],
    desc: "Reusable branded poly bags for marts, garments and pharmacies." },
  { id: "courier-mailer", cat: "poly", name: "Tamper-proof Courier Bag", price: 7, moq: 2000, unit: "bag", img: "courier-mailer", badge: "Best Seller",
    sizes: ["6×9\"", "10×14\"", "12×16\"", "16×20\""], material: "Co-extruded LDPE, 50–60 micron", features: ["Permanent adhesive seal", "Opaque grey inner", "POD pocket option", "Custom logo print"],
    desc: "Secure, waterproof mailing bags for e-commerce and courier companies." },
  { id: "garbage-bag", cat: "poly", name: "Heavy Duty Garbage Bag", price: 9, moq: 2000, unit: "bag", img: "garbage-bag",
    sizes: ["Small", "Medium", "Large", "Jumbo"], material: "HDPE / LLDPE", features: ["Leak-proof star seal", "On-roll supply", "Black or colour coded", "Hospital grade option"],
    desc: "Strong refuse bags for homes, hospitals, hotels and municipalities." },
  { id: "stretch-film", cat: "poly", name: "Pallet Stretch Film", price: 520, moq: 20, unit: "roll", img: "stretch-film",
    sizes: ["18\" Hand", "20\" Machine", "Custom"], material: "Cast LLDPE, 17–23 micron", features: ["300% pre-stretch", "Clingy on one side", "Clear or black", "Low noise unwind"],
    desc: "Load-securing stretch wrap for palletised shipments and warehousing." },
  { id: "shrink-film", cat: "poly", name: "Shrink Wrap Film", price: 480, moq: 50, unit: "kg", img: "shrink-film",
    sizes: ["Tube", "Sheet", "Centre-fold"], material: "LDPE / POF", features: ["High clarity", "Uniform shrink", "Printed option", "Tunnel & gun compatible"],
    desc: "Shrink film for bottle collation, multipacks, beverages and pallets." },

  // Eco & accessories
  { id: "jute-bag", cat: "eco", name: "Natural Jute Bag", price: 140, moq: 300, unit: "bag", img: "jute-bag", badge: "Eco",
    sizes: ["Tote", "Shopper", "Wine", "Gift"], material: "Natural jute with cotton handles", features: ["Biodegradable", "Screen-printed logo", "Laminated inside option", "Reusable for years"],
    desc: "Premium sustainable bags for corporate gifting, events and eco-conscious retail." },
  { id: "non-woven-bag", cat: "eco", name: "Non-woven Promotional Bag", price: 22, moq: 1000, unit: "bag", img: "non-woven-bag",
    sizes: ["W-cut", "D-cut", "Loop handle", "Box bag"], material: "PP spunbond 60–100 GSM", features: ["Ultrasonic sealing", "Silk-screen print", "Many colours in stock", "Reusable & washable"],
    desc: "Low-cost reusable bags for events, pharma promotions, schools and supermarkets." },
  { id: "packing-tape", cat: "eco", name: "Printed Packing Tape", price: 160, moq: 72, unit: "roll", img: "packing-tape",
    sizes: ["2\" × 100 yd", "3\" × 100 yd"], material: "BOPP with acrylic adhesive", features: ["Custom logo print", "Brown / clear / white", "Strong hold", "Noise-free option"],
    desc: "Branded carton sealing tape that adds security and visibility to every shipment." },
  { id: "bubble-wrap", cat: "eco", name: "Bubble Wrap Roll", price: 1800, moq: 5, unit: "roll", img: "bubble-wrap",
    sizes: ["1 m × 50 m", "1.5 m × 50 m", "Pouches"], material: "LDPE air-bubble film", features: ["10 mm / 20 mm bubbles", "Anti-static pink option", "Perforated rolls", "Bubble pouches"],
    desc: "Protective cushioning for glassware, electronics and fragile goods." },
  { id: "product-labels", cat: "eco", name: "Printed Product Labels", price: 1.5, moq: 5000, unit: "label", img: "product-labels",
    sizes: ["Round", "Square", "Rectangle", "Custom die-cut"], material: "Paper / BOPP / Chromo", features: ["Roll or sheet", "Variable barcodes", "Waterproof options", "Gloss / matte varnish"],
    desc: "Self-adhesive labels for bottles, jars, cartons and barcoding." },
  { id: "food-container", cat: "eco", name: "Takeaway Food Container", price: 15, moq: 1000, unit: "piece", img: "food-container",
    sizes: ["250 ml", "500 ml", "750 ml", "1000 ml"], material: "PP / paperboard", features: ["Microwave safe", "Leak-proof lid", "Branded sleeve option", "Stackable"],
    desc: "Containers for restaurants, caterers and cloud kitchens." },
  { id: "egg-tray", cat: "eco", name: "Moulded Pulp Egg Tray", price: 8, moq: 2000, unit: "tray", img: "egg-tray",
    sizes: ["30 cell", "12 cell", "6 cell"], material: "Recycled moulded pulp", features: ["Shock absorbent", "Stackable", "Biodegradable", "Bulk supply"],
    desc: "Eco-friendly trays for poultry farms, distributors and retail egg packs." }
];

const INDUSTRIES = [
  { name: "Food & Grains", icon: "wheat", text: "Rice, flour, sugar, pulses, spices and snacks." },
  { name: "Pharmaceutical", icon: "pill", text: "Cartons, labels, leaflets and blister cartons." },
  { name: "Cement & Construction", icon: "building", text: "Valve bags, jumbo bags and heavy sacks." },
  { name: "Agriculture & Feed", icon: "sprout", text: "Fertilizer, seed and animal feed packaging." },
  { name: "E-commerce & Retail", icon: "cart", text: "Mailers, courier bags and shopping bags." },
  { name: "Chemicals & Minerals", icon: "flask", text: "Lined sacks and FIBC bulk bags." },
  { name: "Textile & Export", icon: "shirt", text: "5-ply export cartons and poly packing." },
  { name: "FMCG & Cosmetics", icon: "sparkle", text: "Premium cartons, pouches and labels." }
];

// Portfolio items. Replace "img" with photos of real delivered jobs.
const PROJECTS = [
  { title: "Basmati Rice Export Range", cat: "bopp", client: "Rice exporter, Karachi", img: "bopp-rice-bag", text: "8-colour BOPP bags in 5, 10 and 25 KG with handle and window." },
  { title: "Cement Valve Bag Programme", cat: "pp-woven", client: "Building materials manufacturer", img: "pp-cement-bag", text: "Block-bottom valve bags for high-speed rotary packers." },
  { title: "Online Store Unboxing Kit", cat: "corrugated", client: "Fashion e-commerce brand", img: "printed-ecom-box", text: "Printed mailer boxes, tissue, tape and courier bags." },
  { title: "Pharma Carton Line", cat: "cartons", client: "Pharmaceutical manufacturer", img: "pharma-carton", text: "Braille-embossed cartons with tamper-evident closures." },
  { title: "Dry Fruit Pouches", cat: "flexible", client: "Gourmet food brand", img: "standup-pouch", text: "Kraft stand-up pouches with zipper and clear window." },
  { title: "Mineral Bulk Packaging", cat: "fibc", client: "Mining & minerals exporter", img: "fibc-jumbo-bag", text: "1-ton FIBC bags with spout top & discharge bottom." },
  { title: "Eco Retail Bags", cat: "paper", client: "Supermarket chain", img: "kraft-paper-bag", text: "Kraft bags replacing single-use plastic at checkout." },
  { title: "Courier Mailer Supply", cat: "poly", client: "Logistics company", img: "courier-mailer", text: "Tamper-proof mailers delivered monthly in 4 sizes." },
  { title: "Luxury Cosmetic Boxes", cat: "cartons", client: "Skincare brand", img: "cosmetic-carton", text: "Soft-touch cartons with hot-foil and spot UV." }
];

// Customer feedback shown on the home page.
// TODO: replace with genuine quotes from Zainco customers before going live.
const TESTIMONIALS = [
  { quote: "Consistent print quality on our BOPP bags across every batch, and they always deliver on time for our export shipments.", who: "Procurement Manager", org: "Rice Exporter, Karachi" },
  { quote: "They helped us redesign our cartons and we cut breakage in transit noticeably. Very responsive team.", who: "Operations Head", org: "E-commerce Brand" },
  { quote: "From woven sacks to jumbo bags, one supplier for everything. Pricing is competitive and the quality is reliable.", who: "Supply Chain Lead", org: "Agro-industrial Company" },
  { quote: "Their food-grade flour bags and printing look great on the shelf. Samples were ready within days.", who: "Brand Manager", org: "Flour Mill, Sindh" }
];

const FAQS = [
  { q: "What is your minimum order quantity (MOQ)?", a: "MOQ depends on the product — typically 500–1,000 units for boxes and paper bags, 1,000–5,000 for woven and BOPP bags, and 50 pieces for FIBC jumbo bags. Plain stock items can be supplied in smaller quantities." },
  { q: "Can you print our logo and brand design?", a: "Yes. We offer flexo, offset and rotogravure printing up to 10 colours. Send us your artwork (AI, PDF or CDR) and our pre-press team will prepare a digital proof for approval." },
  { q: "How long does production take?", a: "Stock items ship in 1–3 days. Custom printed orders usually take 10–21 working days after artwork approval, depending on quantity and process." },
  { q: "Do you deliver outside Karachi?", a: "We deliver across Pakistan — Lahore, Islamabad, Faisalabad, Multan, Hyderabad, Quetta, Peshawar and more — and support export orders with proper documentation." },
  { q: "Can I get samples before placing a bulk order?", a: "Absolutely. Stock samples are free; custom-printed samples are charged and adjusted against your confirmed order." },
  { q: "What payment methods do you accept?", a: "Bank transfer, cheque, cash on delivery for local orders and LC / advance TT for export orders. Credit terms are available for regular corporate clients." }
];
