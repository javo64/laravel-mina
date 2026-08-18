<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sistema en la nube Mina</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/mina.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mina-enhancements.css') }}">
</head>
<body>
<main class="login">
    <section class="cover">
        <div class="cover-brand"><b>F</b><strong>FABULOSA</strong></div>
        <div class="cover-copy">
            <span>PLATAFORMA INTEGRAL</span>
            <h1>Sistema en la nube<br>Mina</h1>
            <p>Administra de manera centralizada todos los procesos de tu operación. La plataforma crecerá progresivamente con nuevos módulos.</p>
            <ul>
                <li><i>✓</i><div><strong>Gestión por módulos</strong><small>Procesos organizados en una sola plataforma</small></div></li>
                <li><i>✓</i><div><strong>Información centralizada</strong><small>Seguimiento, responsables y trazabilidad</small></div></li>
                <li><i>✓</i><div><strong>Crecimiento progresivo</strong><small>Nuevas funciones incorporadas paso a paso</small></div></li>
            </ul>
        </div>
        <footer>Desarrollado por <strong>Javal Tecnología</strong></footer>
    </section>
    <section class="login-form">
        <form method="post" action="{{ route('login.attempt') }}">@csrf @if(request()->boolean('mobile'))<input type="hidden" name="mobile" value="1">@endif
            <div class="mobile-logo"><b>F</b><strong>FABULOSA</strong></div>
            <span>SISTEMA EN LA NUBE MINA</span>
            <h2>Inicia sesión</h2>
            <p>Ingresa tus credenciales para acceder a la plataforma.</p>
            @error('email')<div class="login-error">! {{ $message }}</div>@enderror
            <label>Correo electrónico<input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="usuario@empresa.com"></label>
            <label>Contraseña<input type="password" name="password" required placeholder="Ingresa tu contraseña"></label>
            <label class="remember"><input type="checkbox" name="remember"> Recordarme</label>
            <button class="login-btn">{{ request()->boolean('mobile')?'Ingresar a mis cartillas →':'Ingresar al dashboard →' }}</button>
            <div class="demo"><strong>Acceso local inicial</strong><span>admin@mina.local</span><span>Contraseña: Admin2026</span></div>
        </form>
    </section>
</main>
</body>
</html>
