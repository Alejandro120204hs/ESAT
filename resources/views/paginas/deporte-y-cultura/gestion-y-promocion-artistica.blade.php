@extends('layouts.app')

@section('title', 'Gestión y Promoción Artística · ESAT — Formación Técnica Certificada')
@section('meta_description', 'Técnico en Gestión y Promoción Artística en ESAT: formación en desarrollo cultural, organización de proyectos y difusión del arte en danza, teatro, música y artes plásticas.')

@php
    // Drop the real photo in public/assets/img with this exact
    // filename and it replaces the placeholder panel automatically —
    // no code changes needed.
    $heroPhoto = 'gestion-artistica.png';
    $heroPhotoExists = file_exists(public_path('assets/img/' . $heroPhoto));
    $practicePhoto = 'aprender-gestion-artistica.png';
    $practicePhotoExists = file_exists(public_path('assets/img/' . $practicePhoto));

    $outcomes = [
        ['Casas de cultura', 'Apoya la programación cultural en casas de cultura municipales.', 'ico-building', 'orange', 'casa-cultura.jpeg'],
        ['Fundaciones culturales', 'Trabaja en fundaciones y organizaciones dedicadas al arte y la cultura.', 'ico-heart', 'green', 'fundacion-cultural.jpeg'],
        ['Eventos y producciones artísticas', 'Participa en la organización de eventos y producciones artísticas.', 'ico-megaphone', 'navy', 'evento-artistico.jpeg'],
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
                    <svg viewBox="0 0 24 24"><use href="#ico-deporte"/></svg>
                    <span>Foto pendiente — public/assets/img/{{ $heroPhoto }}</span>
                </div>
            @endif

            <div class="section-inner">
                <div class="program-hero-content">
                    <span class="badge badge-double program-hero-badge">
                        <svg viewBox="0 0 24 24"><use href="#ico-deporte"/></svg>
                        Deporte y Cultura
                    </span>
                    <h1>Gestión y Promoción Artística</h1>
                    <p class="program-hero-tagline">El arte que transforma comunidades.</p>
                    <p class="program-hero-lead">Formamos profesionales en desarrollo cultural, organización de proyectos y difusión del arte, fortaleciendo talentos en danza, teatro, música y artes plásticas.</p>
                    <div class="hero-actions">
                        <a href="{{ $wa('Gestión y Promoción Artística') }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
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
                    <p>Aprende a gestionar proyectos culturales y a promover el arte en danza, teatro, música y artes plásticas.</p>
                </div>
                <div class="program-competency-grid">
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="0">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-admin"/></svg></span>
                        <div><h3>Gestión cultural</h3><p>Desarrolla y gestiona iniciativas culturales en tu comunidad.</p></div>
                    </div>
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="80">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-check"/></svg></span>
                        <div><h3>Organización de proyectos</h3><p>Organiza proyectos y eventos artísticos de principio a fin.</p></div>
                    </div>
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="160">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-megaphone"/></svg></span>
                        <div><h3>Difusión artística</h3><p>Promueve y difunde expresiones artísticas y culturales.</p></div>
                    </div>
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="240">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-palette"/></svg></span>
                        <div><h3>Talento en artes escénicas y plásticas</h3><p>Fortalece talentos en danza, teatro, música y artes plásticas.</p></div>
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
                            <svg viewBox="0 0 24 24"><use href="#ico-deporte"/></svg>
                            <span>Foto pendiente — public/assets/img/{{ $practicePhoto }}</span>
                        </div>
                    @endif
                    <div class="program-photo-panel-content">
                        <h3>Formación con supervisión profesional</h3>
                        <p>Practica la organización de proyectos culturales guiado por un equipo profesional.</p>
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
                    <h2>¿Listo para formarte en Gestión y Promoción Artística?</h2>
                    <p>Escríbenos y un asesor te cuenta los siguientes pasos para inscribirte.</p>
                </div>
                <a href="{{ $wa('Gestión y Promoción Artística') }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
                    <svg viewBox="0 0 24 24"><use href="#ico-whatsapp"/></svg>
                    Quiero inscribirme
                </a>
            </div>
        </section>
@endsection
