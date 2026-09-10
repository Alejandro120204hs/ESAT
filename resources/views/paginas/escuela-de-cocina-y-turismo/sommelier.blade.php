@extends('layouts.app')

@section('title', 'Sommelier · ESAT — Formación Técnica Certificada')
@section('meta_description', 'Técnico Sommelier en ESAT: formación en cata, maridaje y servicio de vinos, con conocimientos sensoriales y técnicos de alto nivel.')

@php
    // Drop the real photo in public/assets/img with this exact
    // filename and it replaces the placeholder panel automatically —
    // no code changes needed.
    $heroPhoto = 'sommelier.jpg';
    $heroPhotoExists = file_exists(public_path('assets/img/' . $heroPhoto));
    $practicePhoto = 'sommelier-practica.jpg';
    $practicePhotoExists = file_exists(public_path('assets/img/' . $practicePhoto));

    $outcomes = [
        ['Restaurantes', 'Asesora el maridaje y servicio de vinos en restaurantes.', 'ico-cocina', 'orange', 'sommelier-restaurantes.jpg'],
        ['Hoteles', 'Brinda experiencias de vino de alto nivel en el sector hotelero.', 'ico-building', 'green', 'sommelier-hoteles.jpg'],
        ['Experiencias enológicas', 'Guía eventos y experiencias enológicas para los comensales.', 'ico-heart', 'navy', 'sommelier-enologicas.jpg'],
    ];
@endphp

@section('content')
        <!-- ============ HERO DEL PROGRAMA ============ -->
        <section class="program-hero">
            @if ($heroPhotoExists)
                <img src="{{ asset('assets/img/' . $heroPhoto) }}" class="program-hero-photo" alt="" aria-hidden="true">
                <div class="program-hero-scrim" aria-hidden="true"></div>
            @else
                <div class="program-hero-placeholder" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><use href="#ico-cocina"/></svg>
                    <span>Foto pendiente — public/assets/img/{{ $heroPhoto }}</span>
                </div>
            @endif

            <div class="section-inner">
                <div class="program-hero-content">
                    <span class="badge badge-double program-hero-badge">
                        <svg viewBox="0 0 24 24"><use href="#ico-cocina"/></svg>
                        Cocina y Turismo
                    </span>
                    <h1>Sommelier</h1>
                    <p class="program-hero-tagline">El arte de elevar la experiencia del vino.</p>
                    <p class="program-hero-lead">Formamos profesionales en cata, maridaje y servicio de vinos, desarrollando conocimientos sensoriales y técnicos para ofrecer experiencias gastronómicas de alto nivel.</p>
                    <div class="hero-actions">
                        <a href="{{ $wa('Sommelier') }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
                            <svg viewBox="0 0 24 24"><use href="#ico-whatsapp"/></svg>
                            Quiero inscribirme
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ QUÉ DESARROLLARÁS ============ -->
        <section class="program-section">
            <img src="{{ asset('assets/img/favicon.png') }}" class="section-watermark" alt="" aria-hidden="true">
            <div class="section-inner">
                <div class="section-head" data-aos="fade-up">
                    <h2>Formación práctica</h2>
                    <p>Descubre los conocimientos y técnicas detrás de una gran experiencia con el vino.</p>
                </div>
                <div class="program-competency-grid">
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="0">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-check"/></svg></span>
                        <div><h3>Cata de vinos</h3><p>Desarrolla conocimientos sensoriales para evaluar vinos.</p></div>
                    </div>
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="80">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-cocina"/></svg></span>
                        <div><h3>Maridaje</h3><p>Combina vinos y alimentos para experiencias gastronómicas de alto nivel.</p></div>
                    </div>
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="160">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-heart"/></svg></span>
                        <div><h3>Servicio de vinos</h3><p>Aplica el protocolo y técnica del servicio de vinos.</p></div>
                    </div>
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="240">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-tool"/></svg></span>
                        <div><h3>Formación práctica</h3><p>Expertos del sector que conectan la teoría con la práctica real.</p></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ DÓNDE TRABAJARÁS ============ -->
        <section class="program-section program-section-tint">
            <div class="section-inner">
                <div class="section-head" data-aos="fade-up">
                    <h2>Dónde trabajarás</h2>
                    <p>Egresa listo para desempeñarte en estos entornos.</p>
                </div>
                <div class="program-outcome-grid">
                    @foreach ($outcomes as [$title, $desc, $icon, $accent, $photo])
                        @php $photoExists = file_exists(public_path('assets/img/' . $photo)); @endphp
                        <article class="program-outcome-card program-outcome-accent-{{ $accent }}" data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                            @if ($photoExists)
                                <img src="{{ asset('assets/img/' . $photo) }}" class="program-outcome-photo" alt="" aria-hidden="true">
                                <div class="program-outcome-scrim" aria-hidden="true"></div>
                            @else
                                <div class="program-outcome-placeholder" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><use href="#{{ $icon }}"/></svg>
                                    <span>Foto pendiente — public/assets/img/{{ $photo }}</span>
                                </div>
                            @endif
                            <div class="program-outcome-content">
                                <span class="program-outcome-icon"><svg viewBox="0 0 24 24"><use href="#{{ $icon }}"/></svg></span>
                                <h3>{{ $title }}</h3>
                                <p>{{ $desc }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- ============ FOTO DE PRÁCTICA ============ -->
        <section class="program-section">
            <div class="section-inner">
                <article class="program-photo-panel" data-aos="fade-up">
                    @if ($practicePhotoExists)
                        <img src="{{ asset('assets/img/' . $practicePhoto) }}" class="program-photo-panel-img" alt="" aria-hidden="true">
                        <div class="program-photo-panel-scrim" aria-hidden="true"></div>
                    @else
                        <div class="program-photo-panel-placeholder" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><use href="#ico-cocina"/></svg>
                            <span>Foto pendiente — public/assets/img/{{ $practicePhoto }}</span>
                        </div>
                    @endif
                    <div class="program-photo-panel-content">
                        <h3>Formación con supervisión profesional</h3>
                        <p>Practica la cata y el servicio de vinos guiado por expertos del sector.</p>
                    </div>
                </article>
            </div>
        </section>

        <!-- ============ RESPALDO INSTITUCIONAL ============ -->
        <section class="program-section program-section-tint">
            <div class="section-inner">
                <div class="program-credibility" data-aos="fade-up">
                    <span class="badge badge-double program-credibility-badge">Respaldo oficial</span>
                    <h3>Escuela Nacional de Educación ESAT</h3>
                    <p>Programa de Educación para el Trabajo y el Desarrollo Humano (ETDH), con licencia y respaldo oficial vigentes.</p>
                    <ul class="program-credibility-chips">
                        <li><svg viewBox="0 0 24 24"><use href="#ico-cert"/></svg>Lic. Funcionamiento 5850 · 23 sep. 2019</li>
                        <li><svg viewBox="0 0 24 24"><use href="#ico-shield"/></svg>Secretaría de Educación de Cundinamarca</li>
                        <li><svg viewBox="0 0 24 24"><use href="#ico-building"/></svg>Ministerio de Educación Nacional</li>
                        <li><svg viewBox="0 0 24 24"><use href="#ico-scale"/></svg>Clasificación ETDH</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ============ CTA FINAL ============ -->
        <section class="program-cta">
            <img src="{{ asset('assets/img/favicon.png') }}" class="program-cta-deco" alt="" aria-hidden="true">
            <div class="section-inner program-cta-inner" data-aos="fade-up">
                <div>
                    <h2>¿Listo para formarte como Sommelier?</h2>
                    <p>Escríbenos y un asesor te cuenta los siguientes pasos para inscribirte.</p>
                </div>
                <a href="{{ $wa('Sommelier') }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
                    <svg viewBox="0 0 24 24"><use href="#ico-whatsapp"/></svg>
                    Quiero inscribirme
                </a>
            </div>
        </section>
@endsection
