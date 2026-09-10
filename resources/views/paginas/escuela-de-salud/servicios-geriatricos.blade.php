@extends('layouts.app')

@section('title', 'Servicios Geriátricos · ESAT — Formación Técnica Certificada')
@section('meta_description', 'Técnico en Servicios Geriátricos en ESAT: formación en atención integral, bienestar y cuidado especializado del adulto mayor, con salida a hogares, clínicas y programas de atención.')

@php
    // Drop the real photos in public/assets/img with these exact
    // filenames and they replace the placeholder panels automatically
    // — no code changes needed.
    $heroPhoto = 'servicio-geriatrico.png';
    $heroPhotoExists = file_exists(public_path('assets/img/' . $heroPhoto));
    $practicePhoto = 'aprendiendo-geriatrico.jpeg';
    $practicePhotoExists = file_exists(public_path('assets/img/' . $practicePhoto));
@endphp

@section('content')
        <!-- ============ BREADCRUMB ============ -->
        <!-- ============ HERO DEL PROGRAMA ============ -->
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
                        <svg viewBox="0 0 24 24"><use href="#ico-salud"/></svg>
                        Escuela de Salud
                    </span>
                    <h1>Servicios Geriátricos</h1>
                    <p class="program-hero-tagline">Cuidado digno y humano para el adulto mayor.</p>
                    <p class="program-hero-lead">Formamos profesionales capacitados en la atención integral al adulto mayor, con énfasis en el bienestar, el acompañamiento, el cuidado especializado y el respeto a la dignidad en cada etapa del envejecimiento.</p>
                    <div class="hero-actions">
                        <a href="{{ $wa('Servicios Geriátricos') }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
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
                    <p>Aprende a acompañar, cuidar y proteger la dignidad del adulto mayor en cada etapa de su envejecimiento.</p>
                </div>
                <div class="program-competency-grid">
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="0">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-heart"/></svg></span>
                        <div><h3>Atención integral al adulto mayor</h3><p>Formación centrada en el bienestar y el acompañamiento en cada etapa del envejecimiento.</p></div>
                    </div>
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="80">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-salud"/></svg></span>
                        <div><h3>Cuidado especializado</h3><p>Competencias para brindar cuidado con respeto a la dignidad de la persona mayor.</p></div>
                    </div>
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="160">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-tool"/></svg></span>
                        <div><h3>Formación práctica</h3><p>Instructores expertos que conectan la teoría con la práctica real del oficio.</p></div>
                    </div>
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="240">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-arrow"/></svg></span>
                        <div><h3>Fórmate para cuidar mejor</h3><p>Formación pensada para que brindes una atención más completa al adulto mayor.</p></div>
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
                    <article class="program-outcome-card program-outcome-accent-orange" data-aos="fade-up" data-aos-delay="0">
                        <img src="{{ asset('assets/img/hogares-geriatricos.jpeg') }}" class="program-outcome-photo" alt="" aria-hidden="true">
                        <div class="program-outcome-scrim" aria-hidden="true"></div>
                        <div class="program-outcome-content">
                            <span class="program-outcome-icon"><svg viewBox="0 0 24 24"><use href="#ico-building"/></svg></span>
                            <h3>Hogares geriátricos</h3>
                            <p>Acompaña el día a día de los residentes: actividades, cuidado personal y bienestar constante.</p>
                        </div>
                    </article>
                    <article class="program-outcome-card program-outcome-accent-green" data-aos="fade-up" data-aos-delay="80">
                        <img src="{{ asset('assets/img/clinica-adulto-mayor.jpeg') }}" class="program-outcome-photo" alt="" aria-hidden="true">
                        <div class="program-outcome-scrim" aria-hidden="true"></div>
                        <div class="program-outcome-content">
                            <span class="program-outcome-icon"><svg viewBox="0 0 24 24"><use href="#ico-salud"/></svg></span>
                            <h3>Clínicas</h3>
                            <p>Apoya la atención clínica del adulto mayor junto a un equipo de salud especializado.</p>
                        </div>
                    </article>
                    <article class="program-outcome-card program-outcome-accent-navy" data-aos="fade-up" data-aos-delay="160">
                        <img src="{{ asset('assets/img/programa-atencion-adulto-mayor.jpeg') }}" class="program-outcome-photo" alt="" aria-hidden="true">
                        <div class="program-outcome-scrim" aria-hidden="true"></div>
                        <div class="program-outcome-content">
                            <span class="program-outcome-icon"><svg viewBox="0 0 24 24"><use href="#ico-heart"/></svg></span>
                            <h3>Programas de atención al adulto mayor</h3>
                            <p>Participa en programas sociales y comunitarios que promueven una vejez activa y feliz.</p>
                        </div>
                    </article>
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
                            <svg viewBox="0 0 24 24"><use href="#ico-heart"/></svg>
                            <span>Foto pendiente — public/assets/img/{{ $practicePhoto }}</span>
                        </div>
                    @endif
                    <div class="program-photo-panel-content">
                        <h3>Formación con supervisión profesional</h3>
                        <p>Practica el cuidado del adulto mayor guiado por un equipo profesional.</p>
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
                    <h2>¿Listo para formarte en Servicios Geriátricos?</h2>
                    <p>Escríbenos y un asesor te cuenta los siguientes pasos para inscribirte.</p>
                </div>
                <a href="{{ $wa('Servicios Geriátricos') }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
                    <svg viewBox="0 0 24 24"><use href="#ico-whatsapp"/></svg>
                    Quiero inscribirme
                </a>
            </div>
        </section>
@endsection
