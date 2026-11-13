<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Código para confirmar tu nuevo correo</title></head>
<body style="margin:0;background:#f4f5f8;font-family:Arial,sans-serif;color:#18233d">
    <main style="max-width:560px;margin:24px auto;padding:32px;background:#fff;border-bottom:4px solid #ffd230;text-align:center">
        <img src="{{ $message->embed(public_path('logo-header.png')) }}" width="220" alt="Super El Rey">
        <h1 style="font-size:24px;color:#2552c3">Confirma tu nuevo correo</h1>
        <p>Escribe este código en ReyPass para usar este correo en la cuenta principal:</p>
        <p style="font-size:40px;letter-spacing:8px;font-weight:bold;color:#2552c3">{{ $code }}</p>
        <p>Vence en 10 minutos.</p>
        <p>Si no lo pediste, ignora este correo: nada cambia sin el código.</p>
    </main>
</body>
</html>
