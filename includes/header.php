<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reserva de Entradas - Teatro Villamartín</title>

  <base href="/teatro/">
  
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Roboto+Slab:wght@300;400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    .logo-img-placeholder { width: 120px !important; height: auto; transition: transform 0.3s ease; }
    .logo-img-placeholder img { width: 100%; height: auto; object-fit: contain; }
    .brand-text span:last-child { font-size: 22px !important; letter-spacing: 0.5px; }

    /* --- BANNER 728x90 --- */
    .banner-section {
        width: 100%;
        max-width: 728px;
        height: auto;
        overflow: hidden;
        border-radius: 10px;
        margin: 0 auto 40px; /* Centrado horizontalmente */
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        background-color: #fff;
    }
    .banner-section img {
        width: 100%;
        height: auto;
        display: block;
        object-fit: contain; /* Garantiza que se vea completa sin cortes */
    }

    .obras-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(310px, 310px));
        gap: 35px;
        justify-content: center;
        max-width: 1100px;
        margin: 0 auto;
    }

    .img-wrapper {
        width: 100%;
        height: 420px !important;
        overflow: hidden;
        border-radius: 8px 8px 0 0;
    }
    .img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
    .obra-item:hover .img-wrapper img { transform: scale(1.05); }

    .obra-item { 
        background: #fff;
        border: 1px solid #eee;
        border-radius: 8px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex;
        flex-direction: column;
    }
    .obra-item:hover { transform: translateY(-8px); box-shadow: 0 12px 25px rgba(0,0,0,0.1); }

    .obra-info-extra { padding: 15px; background: #fcfcfc; border-top: 1px solid #eee; margin-top: auto; }
    .pase-item { font-size: 0.85rem; color: #555; margin-bottom: 5px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .pase-item i { color: #557996; width: 14px; }
    .duracion-tag { font-size: 0.8rem; color: #e67e22; font-weight: 700; margin-top: 5px; display: block; }

    .info-box { background: #fff; border-radius: 8px; padding: 25px; margin-top: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #eee; border-left: 4px solid #557996; }
    .info-box h3 { font-family: 'Roboto Slab', serif; color: #2c3e50; margin-bottom: 15px; border-bottom: 2px solid #557996; display: inline-block; padding-bottom: 5px; }
    .info-box ul { list-style: none; padding: 0; }
    .info-box li { position: relative; padding-left: 20px; margin-bottom: 10px; color: #666; font-size: 0.95rem; line-height: 1.5; }
    .info-box li::before { content: '•'; position: absolute; left: 0; color: #557996; font-weight: bold; }

    html, body { height: 100%; margin: 0; padding: 0; }
    body { display: flex; flex-direction: column; min-height: 100vh; font-family: 'Roboto', sans-serif; background-color: #557996; }
    .white-container { flex: 1 0 auto; padding: 40px 20px; background: white; width: 95%; max-width: 1300px; margin: 0 auto; }

    .main-footer {
        flex-shrink: 0;
        background: #344d65;
        color: white;
        padding: 50px 0 20px;
        margin-top: 60px;
        width: 100%;
    }

    .footer-container { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 40px; padding: 0 20px; }
    .footer-section h4 { font-family: 'Roboto Slab', serif; margin-bottom: 20px; font-size: 1.1rem; text-transform: uppercase; border-bottom: 1px solid #557996; display: inline-block; padding-bottom: 5px; }
    .footer-section p, .footer-section li { font-size: 0.9rem; color: #bdc3c7; line-height: 1.8; list-style: none; }
    .footer-section i { margin-right: 10px; color: #557996; }
    .footer-bottom { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.8rem; color: #7f8c8d; }
  
    /* --- EFECTOS HOVER PARA EL HEADER --- */
    
    /* Efecto en los iconos rrss */
    .social-icons a {
        transition: transform 0.3s ease, color 0.3s ease;
        display: inline-block;
    }
    .social-icons a:hover {
        transform: translateY(-3px) scale(1.1);
        color: #f1c40f !important; /* Un tono dorado/amarillo al pasar el ratón */
    }

    /* Efecto en los enlaces del menú principal */
    .main-nav ul li a {
        position: relative;
        padding-bottom: 5px;
        transition: color 0.3s ease;
    }
    .main-nav ul li a:hover {
        color: #f1c40f !important;
    }

    /* Subrayado animado en el menú */
    .main-nav ul li a::after {
        content: '';
        position: absolute;
        width: 0;
        height: 2px;
        bottom: 0;
        left: 0;
        background-color: #f1c40f;
        transition: width 0.3s ease;
    }
    .main-nav ul li a:hover::after {
        width: 100%;
    }
  </style>
</head>
<body>

  <header class="top-bar">
    <div class="header-container">
      <div class="logo-area">
        <div class="logo-img-placeholder">
            <img src="assets/img/logo.jpg" alt="Escudo Villamartín">
        </div>
        <div class="brand-text">
            <span>EXCMO. AYUNTAMIENTO</span>
            <span>DE VILLAMARTÍN</span>
        </div>
      </div>
      
      <div class="nav-area" style="display: flex; flex-direction: column; align-items: flex-end; gap: 10px;">
        <div class="social-icons" style="display: flex; gap: 15px;">
            <a href="https://www.facebook.com/ayuntamiento.devillamartin/" target="_blank" style="color: white; font-size: 18px;"><i class="fab fa-facebook-f"></i></a>
            <a href="https://x.com/AytoVillamartin" target="_blank" style="color: white; font-size: 18px;"><i class="fab fa-twitter"></i></a>
            <a href="https://www.instagram.com/aytovillamartin/" target="_blank" style="color: white; font-size: 18px;"><i class="fab fa-instagram"></i></a>
        </div>
        
        <nav class="main-nav">
          <ul style="display: flex; list-style: none; gap: 20px;">
            <li><a href="https://www.villamartin.es/" style="color: white; text-decoration: none; font-weight: 700; font-size: 13px;">AYUNTAMIENTO</a></li>
            <li><a href="index.php" style="color: white; text-decoration: none; font-weight: 700; font-size: 13px;">INICIO TEATRO</a></li>
            <li><a href="admin/admin_eventos.php" style="color: white; text-decoration: none; font-weight: 700; font-size: 13px;">ADMIN</a></li>
            <li><a href="https://www.villamartin.es/ayuntamiento/telefonos-y-direcciones" style="color: white; text-decoration: none; font-weight: 700; font-size: 13px;">CONTACTO</a></li>
          </ul>
        </nav>
      </div>
    </div>
  </header>