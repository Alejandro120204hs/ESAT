@extends('layouts.app')

@section('title', 'Pedagogía · Educación Continuada · ESAT')
@section('meta_description', 'Diplomados y cursos de Educación Continuada en Pedagogía en ESAT: competencias educativas, didácticas y humanas para orientar procesos de enseñanza.')

@php
    // Drop the real photo in public/assets/img with this exact
    // filename and it replaces the placeholder panel automatically —
    // no code changes needed.
    $heroPhoto = 'pedagogia.png';
    $heroPhotoExists = file_exists(public_path('assets/img/' . $heroPhoto));

    $courses = [
        'Docencia Universitaria',
        'Estrategias Pedagógicas',
        'Educación Sexual',
        'Instructor de Danza Folclórica',
        'Pedagogía e Investigación en Danzas Folclóricas del Mundo',
        'Entrenador y Preparador Físico en Natación',
        'Entrenador y Preparador Físico en Fútbol Juvenil e Infantil y en Futsal',
        'Medicina Deportiva, Masajes Terapéuticos y Rehabilitación de Lesiones Deportivas',
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
                    <svg viewBox="0 0 24 24"><use href="#ico-cert"/></svg>
                    <span>Foto pendiente — public/assets/img/{{ $heroPhoto }}</span>
                </div>
            @endif

            <div class="section-inner">
                <div class="program-hero-content">
                    <span class="badge badge-double program-hero-badge">
                        <svg viewBox="0 0 24 24"><use href="#ico-cert"/></svg>
                        Educación Continuada
                    </span>
                    <h1>Pedagogía</h1>
                    <p class="program-hero-tagline">Forma y transforma el futuro a través de la educación.</p>
                    <p class="program-hero-lead">El programa de Pedagogía desarrolla competencias educativas, didácticas y humanas, formando profesionales capaces de orientar procesos de enseñanza, aprendizaje y acompañamiento formativo en distintos contextos educativos.</p>
                    <div class="hero-actions">
                        <a href="{{ $wa('Educación Continuada — Pedagogía') }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
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
                    <h2>¿Listo para formarte en Pedagogía?</h2>
                    <p>Escríbenos y un asesor te cuenta los siguientes pasos para inscribirte.</p>
                </div>
                <a href="{{ $wa('Educación Continuada — Pedagogía') }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
                    <svg viewBox="0 0 24 24"><use href="#ico-whatsapp"/></svg>
                    Quiero inscribirme
                </a>
            </div>
        </section>
@endsection
