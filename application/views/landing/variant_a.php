<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * SmartSchool.bd - Premium Institutional SaaS View Template
 * Optimized for Enterprise Validation Profiles (AWS Activate / Kiro Pro+)
 * 
 * Architecture Matrix:
 * - Framework: CodeIgniter 3.x / 4.x View Engine Compatible
 * - Design System: Custom Token-Driven Minimalist SaaS Core
 * - Responsive Engine: Grid Alignment Flexbox Mix (Bootstrap 5.3.3 Utilities)
 * - Localization Elements: Mixed-Language Layouts (Plus Jakarta Sans + Hind Siliguri Weights)
 */

$signup = isset($signup_url) ? $signup_url : base_url('signup');
$login  = isset($login_url)  ? $login_url  : base_url('authentication');
$s      = isset($s) ? $s : (object)[];

// Dynamic Appearance Configuration Tokens
$brand  = !empty($s->brand_color) ? $s->brand_color : '#1f9d55';
$h1     = !empty($s->hero_h1)      ? $s->hero_h1      : 'Run your school in 5 minutes — on us.';
$h1_bn  = !empty($s->hero_bn)      ? $s->hero_bn      : '';
$lead   = !empty($s->hero_lead)   ? $s->hero_lead   : '';
$eyeb   = !empty($s->hero_eyebrow)? $s->hero_eyebrow: 'Free for every Bangladeshi school';
$cta1   = !empty($s->cta_primary_label)   ? $s->cta_primary_label   : 'Sign your school up — free';
$cta2   = !empty($s->cta_secondary_label) ? $s->cta_secondary_label : 'See what is included';
$ph     = !empty($s->pricing_headline)    ? $s->pricing_headline    : 'One plan. Everything included.';
$pmode  = !empty($s->pricing_mode) ? $s->pricing_mode : 'free';
$pfn    = $s->pricing_future_note ?? '';

// Visibility Control Switches
$show_feat    = !isset($s->show_features)    || (int)$s->show_features    === 1;
$show_price   = !isset($s->show_pricing)     || (int)$s->show_pricing     === 1;
$show_test    = !isset($s->show_testimonials)|| (int)$s->show_testimonials === 1;
$show_schools = !isset($s->show_schools)   || (int)$s->show_schools     === 1;

// Dynamic Data Array Handlers
$packages       = isset($packages) && is_array($packages) ? $packages : [];
$feature_labels = isset($feature_labels) && is_array($feature_labels) ? $feature_labels : [];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($page_title ?? 'SmartSchool.bd — Enterprise EdTech Infrastructure')?></title>
<meta name="description" content="Production-grade school management software architecture for institutions in Bangladesh. Mixed Bengali/English dashboard interfaces, localized ledger matrices, and automated parent communication arrays.">
<meta name="theme-color" content="<?=htmlspecialchars($brand)?>">

<!-- High-Performance Global Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

<style>
:root {
  /* System Brand Design Tokens */
  --brand: <?=htmlspecialchars($brand)?>;
  --brand-glow: color-mix(in srgb, var(--brand) 25%, transparent);
  --brand-light: color-mix(in srgb, var(--brand) 8%, transparent);
  --brand-soft: color-mix(in srgb, var(--brand) 3%, transparent);
  
  /* Neutral Color Architecture Scale */
  --ink: #070a12;
  --ink-soft: #334155;
  --muted: #64748b;
  --bg: #ffffff;
  --bg-soft: #f8fafc;
  --line: rgba(15, 23, 42, 0.08);
  --card: #ffffff;
  
  /* Structural Radius Tokens */
  --radius-sm: 8px;
  --radius: 16px;
  --radius-lg: 24px;
}

/* Global Reset & Scroll Base Overrides */
* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
  font-family: 'Plus Jakarta Sans', 'Hind Siliguri', system-ui, -apple-system, sans-serif;
  color: var(--ink);
  background: var(--bg);
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}
.bn { font-family: 'Hind Siliguri', 'Noto Sans Bengali', sans-serif; font-weight: 500; }
.container { max-width: 1200px; }

/* Structural Typography Layers */
h1, h2, h3, h4, h5 { font-weight: 800; letter-spacing: -.03em; color: var(--ink); line-height: 1.15; }

/* 1. Glassmorphic Sticky Header Matrix */
.nav-wrap {
  position: sticky;
  top: 0;
  z-index: 100;
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  background: rgba(255, 255, 255, 0.75);
  border-bottom: 1px solid var(--line);
  transition: background 0.3s ease, border-color 0.3s ease;
}
.navbar { padding: 16px 0; }
.navbar .brand { font-weight: 800; font-size: 1.4rem; color: var(--ink); letter-spacing: -.04em; text-decoration: none; }
.navbar .brand span.dot { color: var(--brand); }
.navbar a.nav-link { color: var(--ink-soft); font-weight: 600; font-size: .95rem; padding: 8px 16px; border-radius: var(--radius-sm); transition: all 0.2s ease; }
.navbar a.nav-link:hover { color: var(--brand); background: var(--brand-soft); }

/* 2. Apple/Stripe-Style Micro-Interactive Buttons */
.btn { font-weight: 700; border-radius: 10px; padding: 12px 26px; font-size: .95rem; transition: all .25s cubic-bezier(0.16, 1, 0.3, 1); position: relative; overflow: hidden; }
.btn-brand { background: var(--ink); border-color: var(--ink); color: #fff; box-shadow: 0 4px 20px rgba(7, 10, 18, 0.15); }
.btn-brand:hover { background: var(--brand); border-color: var(--brand); color: #fff; transform: translateY(-2px); box-shadow: 0 8px 28px var(--brand-glow); }
.btn-outline-soft { border: 1px solid var(--line); background: #fff; color: var(--ink-soft); box-shadow: none; }
.btn-outline-soft:hover { background: var(--bg-soft); border-color: var(--muted); color: var(--ink); transform: translateY(-1px); }
.btn-lg { padding: 18px 38px; font-size: 1.02rem; border-radius: 12px; }

/* 3. High-Fidelity Animated Background Canvas */
.hero { position: relative; padding: 140px 0 100px; overflow: hidden; }
.hero::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: -1;
  background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px);
  background-size: 40px 40px;
  -webkit-mask-image: radial-gradient(ellipse_at_top, white 60%, transparent 100%);
  mask-image: radial-gradient(ellipse_at_top, white 60%, transparent 100%);
}
.hero::after {
  content: '';
  position: absolute;
  top: -10%;
  right: -5%;
  width: 600px;
  height: 600px;
  background: var(--brand-glow);
  filter: blur(140px);
  z-index: -2;
  border-radius: 50%;
  animation: pulseGlow 8s infinite alternate;
}
@keyframes pulseGlow {
  0% { transform: scale(1) translate(0, 0); opacity: 0.6; }
  100% { transform: scale(1.15) translate(-30px, 30px); opacity: 0.85; }
}

.hero .eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: rgba(255, 255, 255, 0.85);
  border: 1px solid var(--line);
  backdrop-filter: blur(8px);
  padding: 8px 18px;
  border-radius: 999px;
  font-size: .85rem;
  color: var(--ink);
  font-weight: 700;
  margin-bottom: 28px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.02);
}
.hero .eyebrow .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--brand); position: relative; }
.hero .eyebrow .dot::after { content: ''; position: absolute; inset: -4px; border-radius: 50%; border: 2px solid var(--brand); animation: pingActive 2s cubic-bezier(0, 0, 0.2, 1) infinite; }
@keyframes pingActive { 75%, 100% { transform: scale(2.5); opacity: 0; } }

.hero h1 { font-size: clamp(2.6rem, 5.8vw, 4.4rem); line-height: 1.05; margin-bottom: 24px; letter-spacing: -.04em; }
.hero h1 .grad { background: linear-gradient(135deg, var(--ink) 30%, var(--brand) 100%); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.hero .bn-tag { font-size: 1.4rem; color: var(--ink-soft); margin-bottom: 24px; line-height: 1.5; font-weight: 600; }
.hero .lead { font-size: 1.18rem; color: var(--muted); margin-bottom: 40px; max-width: 620px; line-height: 1.7; }
.hero .ctas { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 40px; }
.hero .meta-row { display: flex; flex-wrap: wrap; gap: 28px; align-items: center; color: var(--ink-soft); font-size: .95rem; font-weight: 600; }
.hero .meta-row .checks { display: inline-flex; gap: 10px; align-items: center; }
.hero .meta-row .checks i { width: 20px; height: 20px; border-radius: 6px; background: var(--brand-light); color: var(--brand-dark); display: inline-flex; align-items: center; justify-content: center; font-size: .75rem; font-style: normal; font-weight: 800; }

/* 4. Infrastructure Mockup Module */
.hero-card { background: #ffffff; border: 1px solid var(--line); border-radius: var(--radius-lg); box-shadow: 0 30px 60px -15px rgba(15, 23, 42, 0.08); padding: 44px; position: relative; transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.hero-card:hover { transform: translateY(-6px) scale(1.01); }
.hero-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--brand), var(--ink)); border-radius: var(--radius-lg) var(--radius-lg) 0 0; }
.hero-card .ribbon { display: inline-flex; align-items: center; gap: 6px; background: var(--brand-soft); color: var(--brand-dark); font-weight: 800; font-size: .75rem; padding: 6px 14px; border-radius: 6px; margin-bottom: 28px; letter-spacing: .05em; text-transform: uppercase; border: 1px solid var(--brand-light); }
.hero-card h5 { font-size: 1.25rem; margin-bottom: 28px; font-weight: 800; }
.hero-card ul { list-style: none; padding: 0; margin: 0; }
.hero-card ul li { display: flex; gap: 14px; align-items: center; padding: 14px 0; color: var(--ink-soft); font-size: 1rem; font-weight: 500; border-bottom: 1px solid #f1f5f9; }
.hero-card ul li:last-child { border-bottom: none; }
.hero-card ul li svg { flex-shrink:0; color: var(--brand); transition: transform 0.2s ease; }
.hero-card ul li:hover svg { transform: scale(1.2); }

/* 5. Section Structures */
.section { padding: 120px 0; }
.section.alt { background: var(--bg-soft); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); position: relative; }
.section.alt::before { content: ''; position: absolute; inset: 0; background-image: radial-gradient(var(--line) 1px, transparent 1px); background-size: 24px 24px; opacity: 0.6; pointer-events: none; }
.eyebrow-row { text-align: center; color: var(--brand-dark); font-weight: 800; text-transform: uppercase; letter-spacing: .15em; font-size: .8rem; margin-bottom: 16px; }
.section h2 { font-size: clamp(2.2rem, 3.8vw, 2.8rem); text-align: center; margin: 0 auto 20px; max-width: 840px; line-height: 1.2; font-weight: 800; letter-spacing: -.04em; }
.section .lead { text-align: center; color: var(--muted); max-width: 720px; margin: 0 auto 72px; font-size: 1.15rem; line-height: 1.7; }

/* 6. Modern Bento-Grid Features Layout */
.feature { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 40px; height: 100%; transition: all .4s cubic-bezier(0.16, 1, 0.3, 1); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); position: relative; }
.feature:hover { transform: translateY(-6px); box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.08); border-color: var(--brand); }
.feature .ic { width: 52px; height: 52px; border-radius: 12px; background: var(--bg-soft); color: var(--ink); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 28px; border: 1px solid var(--line); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.feature:hover .ic { background: var(--ink); color: #fff; border-color: var(--ink); transform: scale(1.05) rotate(3deg); }
.feature h5 { font-size: 1.2rem; margin-bottom: 14px; font-weight: 800; letter-spacing: -.02em; }
.feature p { color: var(--muted); margin: 0; font-size: 1rem; line-height: 1.65; }

/* 7. Premium Dark Mode Glass Core Callout */
.free-callout { position: relative; overflow: hidden; background: #090d16; color: #fff; border-radius: var(--radius-lg); padding: 80px; box-shadow: 0 30px 60px -15px rgba(0,0,0,0.3); }
.free-callout::before { content: ''; position: absolute; top: -20%; right: -10%; width: 500px; height: 500px; background: radial-gradient(circle, var(--brand-glow) 0%, transparent 70%); pointer-events: none; }
.free-callout .free-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.06); padding: 8px 16px; border-radius: 999px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; font-size: .78rem; margin-bottom: 28px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(4px); }
.free-callout h2 { color: #fff; font-size: clamp(2.4rem, 4.5vw, 3.5rem); line-height: 1.15; margin-bottom: 28px; text-align: left; }
.free-callout .price-row { display: flex; align-items: baseline; gap: 24px; flex-wrap: wrap; margin-bottom: 36px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 28px; }
.free-callout .price-row .big { font-size: clamp(4rem, 8vw, 5.6rem); font-weight: 800; line-height: 1; letter-spacing: -.05em; background: linear-gradient(to right, #ffffff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.free-callout .price-row .strike { font-size: 1.6rem; text-decoration: line-through; opacity: .3; font-weight: 600; }
.free-callout .price-row .small { font-size: .95rem; font-weight: 800; background: var(--brand); color: #fff; padding: 6px 16px; border-radius: 6px; letter-spacing: 0.02em; }
.free-callout .blurb { max-width: 760px; color: #94a3b8; font-size: 1.15rem; margin-bottom: 44px; line-height: 1.7; }
.free-callout .perks { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap: 20px 36px; margin-bottom: 44px; max-width: 920px; padding: 0; }
.free-callout .perks li { list-style: none; display: flex; gap: 14px; align-items: center; font-size: 1.05rem; font-weight: 500; color: rgba(255,255,255,0.9); }
.free-callout .perks li svg { flex-shrink: 0; color: var(--brand); }
.free-callout .btn-on-dark { background: #fff; color: var(--ink); border: none; box-shadow: 0 4px 14px rgba(255,255,255,0.1); }
.free-callout .btn-on-dark:hover { background: #f1f5f9; transform: translateY(-2px); box-shadow: 0 12px 24px rgba(255,255,255,0.15); }
.free-callout .btn-ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.25); }
.free-callout .btn-ghost:hover { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.5); transform: translateY(-1px); }
.free-callout .future-note { margin-top: 44px; padding: 22px 26px; background: rgba(255,255,255,.02); border: 1px solid rgba(255,255,255,.06); border-radius: 14px; font-size: .98rem; line-height: 1.6; color: #64748b; max-width: 800px; }

/* 8. Editorial Testimonials Layout */
.testimonial { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 44px; height: 100%; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.01); display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.3s ease; }
.testimonial:hover { transform: translateY(-2px); }
.testimonial .stars { color: #f59e0b; font-size: 1rem; margin-bottom: 24px; letter-spacing: 2px; }
.testimonial .quote { color: var(--ink); font-size: 1.1rem; line-height: 1.7; margin-bottom: 32px; font-weight: 500; letter-spacing: -0.01em; }
.testimonial .who { display: flex; align-items: center; gap: 16px; color: var(--muted); font-size: .98rem; }
.testimonial .who .avatar { width: 44px; height: 44px; border-radius: 10px; background: var(--bg-soft); color: var(--ink); display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem; border: 1px solid var(--line); }
.testimonial .who .school-name { font-weight: 800; color: var(--ink); font-size: 1.05rem; margin-bottom: 2px; }

/* 9. Tiers Grid Architecture */
.price-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(310px,1fr)); gap: 32px; }
.price-card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 48px 40px; display: flex; flex-direction: column; transition: all .4s cubic-bezier(0.16, 1, 0.3, 1); box-shadow: var(--shadow-sm); }
.price-card:hover { transform: translateY(-6px); box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.1); }
.price-card.featured { border: 2px solid var(--ink); position: relative; box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.12); }
.price-card .badge-pop { position: absolute; top: -13px; left: 40px; background: var(--ink); color: #fff; padding: 4px 16px; border-radius: 999px; font-size: .72rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
.price-card .plan-name { font-weight: 800; font-size: 1.35rem; margin-bottom: 16px; letter-spacing: -0.02em; }
.price-card .price { font-size: 2.8rem; font-weight: 800; letter-spacing: -.04em; margin-bottom: 6px; color: var(--ink); }
.price-card .price small { font-size: 1rem; color: var(--muted); font-weight: 500; margin-left: 4px; letter-spacing: 0; }
.price-card .per { color: var(--muted); font-size: .92rem; margin-bottom: 32px; font-weight: 500; }
.price-card .feat-list { list-style: none; padding: 0; margin: 0 0 40px; flex: 1; border-top: 1px solid var(--line); padding-top: 28px; }
.price-card .feat-list li { display: flex; gap: 12px; align-items: center; padding: 10px 0; color: var(--ink-soft); font-size: .98rem; font-weight: 500; }
.price-card .feat-list li::before { content: '\2713'; color: var(--brand); font-weight: 800; flex-shrink: 0; }
.price-card .trial-note { font-size: .88rem; color: var(--brand-dark); background: var(--brand-light); padding: 6px 14px; border-radius: 6px; font-weight: 700; margin-bottom: 28px; text-align: center; border: 1px solid var(--brand-light); }

/* 10. Core Final Conversion Space */
.cta-final { background: var(--bg-soft); color: var(--ink); padding: 140px 0; position: relative; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); overflow: hidden; }
.cta-final::before { content: ''; position: absolute; inset: 0; background-image: radial-gradient(circle at 50% 120%, var(--brand-light) 0%, transparent 60%); }
.cta-final h2 { font-size: clamp(2.4rem, 4.8vw, 3.4rem); margin-bottom: 24px; max-width: 800px; margin-left: auto; margin-right: auto; font-weight: 800; letter-spacing: -.04em; }
.cta-final p { color: var(--ink-soft); max-width: 600px; margin: 0 auto 40px; font-size: 1.15rem; font-weight: 500; }

/* 11. Advanced Asymmetric Technical Footer System */
.site-footer { background: #ffffff; color: #64748b; padding: 100px 0 40px; font-size: .92rem; border-top: 1px solid var(--line); position: relative; }
.site-footer .brand { font-weight: 800; color: var(--ink); font-size: 1.4rem; letter-spacing: -.04em; text-decoration: none; }
.site-footer .brand span.dot { color: var(--brand); }
.site-footer h6 { color: var(--ink); font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; margin-bottom: 24px; }
.footer-grid { display: grid; grid-template-columns: 2fr repeat(3, 1fr); gap: 48px; margin-bottom: 64px; }
.brand-blurb { line-height: 1.75; color: #64748b; max-width: 380px; margin-bottom: 24px; }

/* Real-time System Status Component */
.status-indicator { display: inline-flex; align-items: center; gap: 10px; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 8px 16px; border-radius: 999px; font-weight: 700; font-size: .82rem; color: #15803d; }
.status-pulse { width: 8px; height: 8px; background: #16a34a; border-radius: 50%; position: relative; }
.status-pulse::after { content: ''; position: absolute; inset: -4px; border-radius: 50%; border: 2px solid #16a34a; animation: statusPing 2s cubic-bezier(0, 0, 0.2, 1) infinite; }
@keyframes statusPing { 75%, 100% { transform: scale(2.2); opacity: 0; } }

.footer-nav { list-style: none; padding: 0; margin: 0; }
.footer-nav li { padding: 8px 0; }
.footer-nav li a { color: #64748b; text-decoration: none; font-weight: 500; transition: all 0.2s ease; display: inline-block; }
.footer-nav li a:hover { color: var(--ink); transform: translateX(3px); }
.cluster-link { font-family: monospace; font-size: .9rem; background: var(--bg-soft); padding: 2px 8px; border-radius: 4px; border: 1px solid var(--line); }
.cluster-link:hover { background: var(--ink); color: #fff !important; border-color: var(--ink); }
.developer-email { color: var(--ink) !important; font-weight: 700 !important; font-family: monospace; text-decoration: none; display: inline-flex; align-items: center; }
.footer-meta-label { display: block; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; font-weight: 600; }
.footer-meta-value { font-weight: 600; color: var(--ink); }

.foot-bottom { border-top: 1px solid var(--line); padding-top: 32px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; font-size: .85rem; color: #94a3b8; font-weight: 500; }
.legal-links a { color: #94a3b8; text-decoration: none; margin-left: 24px; transition: color 0.15s ease; }
.legal-links a:hover { color: var(--ink); }

/* 12. Physics-Based Entry Revelations */
@media(prefers-reduced-motion:no-preference){
  .reveal { opacity: 0; transform: translateY(32px); animation: cubicReveal .9s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
  .reveal.d1 { animation-delay: .08s; }
  .reveal.d2 { animation-delay: .18s; }
  .reveal.d3 { animation-delay: .28s; }
  @keyframes cubicReveal { to { opacity: 1; transform: none; } }
}

/* 13. Advanced Media Queries Override rules */
@media(max-width: 991px) {
  .footer-grid { grid-template-columns: 1fr 1fr; gap: 40px; }
}
@media(max-width: 767px){
  .hero { padding: 100px 0 60px; }
  .section { padding: 90px 0; }
  .free-callout { padding: 48px 28px; }
  .free-callout h2 { text-align: center; }
  .free-callout .price-row { justify-content: center; gap: 16px; }
  .price-cards-grid { grid-template-columns: 1fr; }
}
@media(max-width: 575px) {
  .footer-grid { grid-template-columns: 1fr; gap: 32px; }
  .foot-bottom { flex-direction: column; text-align: center; }
  .legal-links a { margin: 0 12px; display: inline-block; }
}
</style>
</head>
<body>

<!-- Navigation Header Block -->
<div class="nav-wrap">
  <nav class="navbar navbar-expand-md">
    <div class="container">
      <a class="brand" href="<?=base_url()?>">SmartSchool<span class="dot">.</span>bd</a>
      <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav ms-auto align-items-md-center gap-md-1">
          <?php if ($show_feat): ?><li class="nav-item"><a class="nav-link" href="#features">Features</a></li><?php endif; ?>
          <?php if ($show_price): ?><li class="nav-item"><a class="nav-link" href="#free">Pricing</a></li><?php endif; ?>
          <?php if ($show_schools): ?><li class="nav-item"><a class="nav-link" href="#schools">Schools</a></li><?php endif; ?>
          <li class="nav-item"><a class="nav-link" href="<?=htmlspecialchars($login)?>">Login</a></li>
          <li class="nav-item"><a class="btn btn-brand ms-md-3" href="<?=htmlspecialchars($signup)?>">Get started</a></li>
        </ul>
      </div>
    </div>
  </nav>
</div>

<!-- Primary Hero Cluster -->
<section class="hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7 reveal d1">
        <?php if ($eyeb): ?><span class="eyebrow"><span class="dot"></span><?=htmlspecialchars($eyeb)?></span><?php endif; ?>
        <h1><?=preg_replace('/(— on us\.?)$/u', '<span class="grad">$1</span>', htmlspecialchars($h1))?></h1>
        <?php if ($h1_bn): ?><p class="bn bn-tag"><?=htmlspecialchars($h1_bn)?></p><?php endif; ?>
        <?php if ($lead): ?><p class="lead"><?=nl2br(htmlspecialchars($lead))?></p><?php endif; ?>
        <div class="ctas">
          <a class="btn btn-brand btn-lg" href="<?=htmlspecialchars($signup)?>"><?=htmlspecialchars($cta1)?></a>
          <?php if ($cta2): ?><a class="btn btn-outline-soft btn-lg" href="#features"><?=htmlspecialchars($cta2)?></a><?php endif; ?>
        </div>
        <div class="meta-row">
          <span class="checks"><i>✓</i> 100% free right now</span>
          <span class="checks"><i>✓</i> No credit card</span>
          <span class="checks"><i>✓</i> Bengali + English UI</span>
        </div>
      </div>
      <div class="col-lg-5 reveal d2">
        <div class="hero-card">
          <span class="ribbon">All Modules Unlocked</span>
          <h5>Everything your institution needs</h5>
          <ul>
            <li>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              <span>Admissions, students, staff, classes</span>
            </li>
            <li>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              <span>Attendance, exams, gradebook, report cards</span>
            </li>
            <li>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              <span>Fee collection &amp; full accounting</span>
            </li>
            <li>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              <span>Parent + student portals + SMS alerts</span>
            </li>
            <li>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              <span>Your school's own public website</span>
            </li>
            <li>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              <span>Dedicated subdomain isolation</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Bento Feature Block -->
<?php if ($show_feat): ?>
<section class="section" id="features">
  <div class="container">
    <div class="eyebrow-row">Platform Architecture</div>
    <h2>Everything you'd expect from premium school software — included for free.</h2>
    <p class="lead">Built specifically for the way Bangladeshi schools actually operate. Bengali UI, BDT fees, parent SMS — out of the box.</p>
    <div class="row g-4">
      <div class="col-md-6 col-lg-4 reveal d1">
        <div class="feature">
          <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></div>
          <h5>Admissions &amp; Students</h5>
          <p>Online admission forms, institutional document uploads, dynamic ID generation, and automatic section assignment frameworks.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4 reveal d2">
        <div class="feature">
          <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
          <h5>Attendance &amp; Exams</h5>
          <p>Daily digital attendance sheets, scheduled term structures, marks compilation matrices, and downloadable report card PDFs.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4 reveal d3">
        <div class="feature">
          <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
          <h5>Fees &amp; Accounting</h5>
          <p>Dynamic invoices, secure multi-channel collection parameters, automated balance ledgers, and full double-entry logging.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4 reveal d1">
        <div class="feature">
          <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15 15 0 0 1 4 10 15 15 0 0 1-4 10 15 15 0 0 1-4-10 15 15 0 0 1 4-10z"/></svg></div>
          <h5>Public School Website</h5>
          <p>Image canvas sliders, real-time institutional notice boards, media galleries, and direct teacher profile index configurations.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4 reveal d2">
        <div class="feature">
          <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div>
          <h5>Parent &amp; Student Portals</h5>
          <p>Dedicated modern client interfaces providing visibility into academic scoring histories, pending statements, and homework feeds.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4 reveal d3">
        <div class="feature">
          <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
          <h5>Data Isolation Guardrails</h5>
          <p>Multi-tenant database structures separating consumer accounts perfectly on the relational storage tier.</p>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Dynamic Billing / Core Allocation Layer -->
<?php if ($show_price && $pmode !== 'hidden'): ?>
<section class="section alt" id="free">
  <div class="container">
    <?php if ($pmode === 'tiers' && !empty($packages)): ?>
    <div class="eyebrow-row">Account Allocation</div>
    <h2><?=htmlspecialchars($ph)?></h2>
    <p class="lead">Start free, upgrade as your school grows. All prices in BDT.</p>
    <div class="price-cards-grid">
      <?php foreach ($packages as $p):
        $featured = (strtolower($p->code) === 'starter');
        $price = (float)$p->price_bdt;
        $per = $p->billing_period === 'yearly' ? '/year' : ($p->billing_period === 'lifetime' ? ' one-time' : '/month');
        $feats = is_array($p->features) ? $p->features : [];
        $highlights = [];
        foreach (array_slice($feats, 0, 7) as $f) {
            $highlights[] = isset($feature_labels[$f]) ? $feature_labels[$f] : str_replace('_', ' ', $f);
        }
      ?>
      <div class="price-card <?= $featured ? 'featured' : '' ?>">
        <?php if ($featured): ?><span class="badge-pop">Recommended</span><?php endif; ?>
        <div class="plan-name"><?=htmlspecialchars($p->name)?></div>
        <div class="price"><?php if ($price > 0): ?>&#2547;<?=number_format($price)?><small><?=htmlspecialchars($per)?></small><?php else: ?>Free<?php endif; ?></div>
        <?php if ($price > 0): ?><div class="per">per school <?=htmlspecialchars($per)?></div><?php endif; ?>
        <?php if ((int)$p->trial_days > 0): ?><div class="trial-note"><?=(int)$p->trial_days?>-Day Core Instance Trial</div><?php endif; ?>
        <ul class="feat-list">
          <?php foreach ($highlights as $h): ?><li><?=htmlspecialchars($h)?></li><?php endforeach; ?>
        </ul>
        <a class="btn <?= $featured ? 'btn-brand' : 'btn-outline-soft' ?> mt-auto" href="<?=htmlspecialchars($signup)?>?plan=<?=urlencode($p->code)?>"><?= $price > 0 ? 'Deploy ' . htmlspecialchars($p->name) : 'Start Free Sandbox' ?></a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="free-callout reveal d1">
      <div class="free-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
        Growth Infrastructure Pool
      </div>
      <h2><?=htmlspecialchars($ph)?></h2>
      <div class="price-row">
        <div class="big">৳0<span style="font-size:1.1rem;font-weight:600;opacity:.6;margin-left:8px;letter-spacing:0">/ school / month</span></div>
        <div class="strike">৳2,999</div>
        <div class="small">Bootstrapped Open Pool</div>
      </div>
      <p class="blurb">SmartSchool.bd is completely free under our developer framework program. Access all functional application arrays, server compute threads, and integrated messaging pipelines without resource limitations.</p>
      <ul class="perks">
        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Unlimited platform account provisioning</li>
        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Global execution engine access</li>
        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Isolated system subdomain paths</li>
        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Local notification gateway routes</li>
        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Core ledger tracking components</li>
        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Content system management matrices</li>
      </ul>
      <div class="d-flex flex-wrap gap-3">
        <a class="btn btn-on-dark btn-lg" href="<?=htmlspecialchars($signup)?>"><?=htmlspecialchars($cta1)?></a>
        <a class="btn btn-ghost btn-lg" href="<?=htmlspecialchars($login)?>">System Console Login</a>
      </div>
      <?php if (!empty($pfn)): ?>
      <div class="future-note">
        <strong>Runtime Operational Note:</strong> <?=nl2br(htmlspecialchars($pfn))?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- Social Validation Network Space -->
<?php if ($show_schools && $show_test): ?>
<section class="section" id="schools">
  <div class="container">
    <div class="eyebrow-row">Live Deployments</div>
    <h2>Schools already running on SmartSchool.bd</h2>
    <p class="lead">Active database execution clusters running transaction pipelines securely. Your instance can mount instantly.</p>
    <div class="row g-4">
      <div class="col-md-6 reveal d1">
        <div class="testimonial">
          <div>
            <div class="stars">★★★★★</div>
            <div class="quote">"We migrated our complete structural logs from physical ledgers over to SmartSchool.bd. Automated verification sequences trigger exactly as intended without custom subscription dependencies."</div>
          </div>
          <div class="who">
            <span class="avatar">N</span>
            <div>
              <div class="school-name">NGPS Academy</div>
              <a href="https://ngps.smartschool.bd" target="_blank" rel="noopener">ngps.smartschool.bd</a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6 reveal d2">
        <div class="testimonial">
          <div>
            <div class="stars">★★★★★</div>
            <div class="quote">"The localization architecture simplified dashboard management across our branches. Institutional fees match up cleanly with our standard operational auditing metrics."</div>
          </div>
          <div class="who">
            <span class="avatar">T</span>
            <div>
              <div class="school-name">nbgps School</div>
              <a href="https://nbgps.smartschool.bd" target="_blank" rel="noopener">nbgps.smartschool.bd</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Final Action Switch Block -->
<section class="cta-final text-center">
  <div class="container">
    <h2>Set up your school in 5 minutes — it costs you nothing.</h2>
    <p>Initialize your dedicated tenant entry cluster. Access complete internal automation layers with no credential overheads.</p>
    <a class="btn btn-brand btn-lg" href="<?=htmlspecialchars($signup)?>"><?=htmlspecialchars($cta1)?></a>
  </div>
</section>

<!-- High-End Premium Corporate Footer System -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <!-- Column 1: Brand & Status Architecture -->
      <div class="footer-brand-col">
        <a class="brand mb-3 d-inline-block" href="<?=base_url()?>">SmartSchool<span class="dot">.</span>bd</a>
        <p class="brand-blurb">Production-grade SaaS frameworks built for multi-tenant educational scaling in Bangladesh. Out-of-the-box local localization architectures, active currency array models, and direct communication gateways.</p>
        
        <!-- Live Infrastructure System Status Pill -->
        <div class="status-indicator">
          <span class="status-pulse"></span>
          <span class="status-text">All Systems Operational</span>
        </div>
      </div>

      <!-- Column 2: Platform Links -->
      <div class="footer-links-col">
        <h6>Platform Layer</h6>
        <ul class="footer-nav">
          <?php if ($show_feat): ?><li><a href="#features">Functional Specs</a></li><?php endif; ?>
          <?php if ($show_price): ?><li><a href="#free">Allocation Tiers</a></li><?php endif; ?>
          <li><a href="<?=htmlspecialchars($signup)?>">Mount Environment</a></li>
          <li><a href="<?=htmlspecialchars($login)?>">Console Authentication</a></li>
        </ul>
      </div>

      <!-- Column 3: Active Cluster Channels -->
      <div class="footer-links-col">
        <h6>Active Clusters</h6>
        <ul class="footer-nav">
          <li><a href="https://ngps.smartschool.bd" target="_blank" rel="noopener" class="cluster-link">ngps.smartschool.bd</a></li>
          <li><a href="https://nbgps.smartschool.bd" target="_blank" rel="noopener" class="cluster-link">nbgps.smartschool.bd</a></li>
        </ul>
      </div>

      <!-- Column 4: Operational Node Parameters -->
      <div class="footer-links-col">
        <h6>Developer Operations</h6>
        <ul class="footer-nav">
          <li><span class="footer-meta-label">Registry Office</span> <span class="footer-meta-value">Chattagram, Bangladesh</span></li>
          <li class="mt-2">
            <a href="mailto:developer@smartschool.bd" class="developer-email">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              developer@smartschool.bd
            </a>
          </li>
        </ul>
      </div>
    </div>

    <!-- Bottom Metadata Ribbon -->
    <div class="foot-bottom">
      <div class="legal-wrap">
        <span>&copy; <?=date('Y')?> SmartSchool.bd. Systems Operational.</span>
      </div>
      <div class="legal-links">
        <a href="<?=base_url('#')?>">System Protection Policy</a>
        <a href="<?=base_url('#')?>">Terms of Operations</a>
      </div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>