@extends('layouts.app')

@section('title', 'Barbería · ESAT — Formación Técnica Certificada')
@section('meta_description', 'Técnico en Barbería en ESAT: formación en corte, arreglo y cuidado masculino, con técnicas modernas y atención al cliente.')

@php
    // Drop the real photo in public/assets/img with this exact
    // filename and it replaces the placeholder panel automatically —
    // no code changes needed.
    $heroPhoto = 'barberia.png';
    $heroPhotoExists = file_exists(public_path('assets/img/' . $heroPhoto));
    $practicePhoto = 'aprender-barberia.png';
    $practicePhotoExists = file_exists(public_path('assets/img/' . $practicePhoto));

    $outcomes = [
        ['Barberías', 'Ejerce tu oficio en barberías reconocidas del sector.', 'ico-tool', 'orange', 'barberia-local.jpeg'],
        ['Salones de belleza', 'Brinda servicios de barbería dentro de salones de belleza.', 'ico-belleza', 'green', 'salon-belleza.jpeg'],
        ['Negocio propio', 'Emprende y gestiona tu propio negocio de barbería.', 'ico-check', 'navy', 'negocio-propio-barberia.jpeg'],
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
                    <svg viewBox="0 0 24 24"><use href="#ico-belleza"/></svg>
                    <span>Foto pendiente — public/assets/img/{{ $heroPhoto }}</span>
                </div>
            @endif

            <div class="section-inner">
                <div class="program-hero-content">
                    <span class="badge badge-double program-hero-badge">
                        <svg viewBox="0 0 24 24"><use href="#ico-belleza"/></svg>
                        Escuela de Belleza
                    </span>
                    <h1>Barbería</h1>
                    <p class="program-hero-tagline">Estilo y precisión en cada corte.</p>
                    <p class="program-hero-lead">Formamos profesionales en corte, arreglo y cuidado masculino, combinando técnicas modernas con atención al cliente para tu desarrollo profesional y emprendedor.</p>
                    <div class="hero-actions">
                        <a href="{{ $wa('Barbería') }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
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
                    <p>Aprende técnicas modernas de corte y cuidado masculino con enfoque en atención al cliente.</p>
                </div>
                <div class="program-competency-grid">
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="0">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-tool"/></svg></span>
                        <div><h3>Técnicas de corte</h3><p>Domina técnicas de corte y arreglo masculino.</p></div>
                    </div>
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="80">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-belleza"/></svg></span>
                        <div><h3>Técnicas modernas de barbería</h3><p>Aplica técnicas modernas y tendencias del sector.</p></div>
                    </div>
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="160">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-heart"/></svg></span>
                        <div><h3>Atención al cliente</h3><p>Brinda una experiencia de servicio memorable a cada cliente.</p></div>
                    </div>
                    <div class="program-competency-card" data-aos="fade-up" data-aos-delay="240">
                        <span class="node"><svg viewBox="0 0 24 24"><use href="#ico-check"/></svg></span>
                        <div><h3>Visión de negocio</h3><p>Desarrolla habilidades para emprender tu propio negocio.</p></div>
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
                            <svg viewBox="0 0 24 24"><use href="#ico-belleza"/></svg>
                            <span>Foto pendiente — public/assets/img/{{ $practicePhoto }}</span>
                        </div>
                    @endif
                    <div class="program-photo-panel-content">
                        <h3>Formación con supervisión profesional</h3>
                        <p>Practica técnicas de corte y arreglo masculino guiado por un equipo profesional.</p>
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
                    <h2>¿Listo para formarte en Barbería?</h2>
                    <p>Escríbenos y un asesor te cuenta los siguientes pasos para inscribirte.</p>
                </div>
                <a href="{{ $wa('Barbería') }}" target="_blank" rel="noopener" class="btn btn-accent btn-lg">
                    <svg viewBox="0 0 24 24"><use href="#ico-whatsapp"/></svg>
                    Quiero inscribirme
                </a>
            </div>
        </section>
@endsection
