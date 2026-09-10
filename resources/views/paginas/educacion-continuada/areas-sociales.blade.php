@extends('layouts.app')

@section('title', 'Áreas Sociales · Educación Continuada · ESAT')
@section('meta_description', 'Diplomados y cursos de Educación Continuada en Áreas Sociales en ESAT: acompañamiento, gestión social y promoción del bienestar colectivo.')

@php
    // Drop the real photo in public/assets/img with this exact
    // filename and it replaces the placeholder panel automatically —
    // no code changes needed.
    $heroPhoto = 'areas-sociales.png';
    $heroPhotoExists = file_exists(public_path('assets/img/' . $heroPhoto));

    $courses = [
        'Psicología Clínica',
        'Sicometría',
        'Nuevo Sistema Penal Acusatorio',
        'Derecho Administrativo',
        'Derecho de Familia',
        'Seguridad Integral',
        'Estudios Bíblicos',
    ];
@endphp

@section('content')
        <!-- ============ HERO DE LA CATEGORÍA ============ -->
        <section class="program-hero">
            @if ($heroPhotoExists)
                <img src="{{ asset('assets/img/' . $heroPhoto) }}" class="program-hero-photo" alt="" aria-hidden="true">
                <div class="program-hero-scrim" aria-hidden="true"></div>
            @else
                <div class="program-hero-placeholder" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><use href="#ico-heart"/></svg>
                    <span>Foto pendiente — public/assets/img/{{ $heroPhoto }}</span>
                </div>
            @endif

            <div class="section-inner">
                <div class="program-hero-content">
                    <span class="badge badge-double program-hero-badge">
                        <svg viewBox="0 0 24 24"><use href="#ico-cert"/></svg>
                        Educación Continuada
                    </span>
                    <h1>Áreas Sociales</h1>
                    <p class="program-hero-tagline">Impacta vidas y transforma comunidades.</p>
                    <p class="program-hero-lead">Desarrollamos competencias para la intervención en procesos comunitarios y sociales, fortaleciendo el acompañamiento, la gestión social y la promoción del bienestar colectivo.</p>
                    <div class="hero-actions">
                        <a href="{{ $wa('Educación Continuada — Áreas Sociales') }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
                            <svg viewBox="0 0 24 24"><use href="#ico-whatsapp"/></svg>
                            Quiero inscribirme
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ DIPLOMADOS Y CURSOS ============ -->
        <section class="program-section">
            <img src="{{ asset('assets/img/favicon.png') }}" class="section-watermark" alt="" aria-hidden="true">
            <div class="section-inner">
                <div class="section-head" data-aos="fade-up">
                    <h2>Diplomados y cursos</h2>
                    <p>Explora las opciones disponibles dentro de esta categoría de Educación Continuada.</p>
                </div>
                <div class="program-courses-grid">
                    @foreach ($courses as $course)
                        <div class="program-course-card" data-aos="fade-up" data-aos-delay="{{ $loop->index * 40 }}">
                            <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-cert"/></svg></span>
                            <span>{{ $course }}</span>
                        </div>
                    @endforeach
                </div>
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
                    <h2>¿Listo para formarte en Áreas Sociales?</h2>
                    <p>Escríbenos y un asesor te cuenta los siguientes pasos para inscribirte.</p>
                </div>
                <a href="{{ $wa('Educación Continuada — Áreas Sociales') }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
                    <svg viewBox="0 0 24 24"><use href="#ico-whatsapp"/></svg>
                    Quiero inscribirme
                </a>
            </div>
        </section>
@endsection
