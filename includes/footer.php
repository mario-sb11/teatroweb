<footer class="main-footer">
      <div class="footer-container">
          <div class="footer-section">
              <h4>Contacto</h4>
              <ul>
                  <li><i class="fas fa-map-marker-alt"></i> Plaza del Ayuntamiento, 1<br>11650 Villamartín (Cádiz)</li>
                  <li>
                      <i class="fas fa-phone"></i> 
                      <?php 
                      
                        if (isset($res_config['contacto_telefono']) && !empty($res_config['contacto_telefono'])) {
                            echo htmlspecialchars($res_config['contacto_telefono']);
                        } else {
                            echo '956 73 00 00';
                        }
                      ?>
                  </li>
                  <li>
                      <i class="fas fa-envelope"></i> 
                      <?php 

                        if (isset($res_config['contacto_correo']) && !empty($res_config['contacto_correo'])) {
                            echo htmlspecialchars($res_config['contacto_correo']);
                        } else {
                            echo 'teatro@villamartin.es';
                        }
                      ?>
                  </li>
              </ul>
          </div>
          <div class="footer-section">
              <h4>Horario de Taquilla</h4>
              <ul>
                  <?php 

                  if(!empty($res_config['horario_linea_1'])): ?>
                      <li><i class="far fa-clock"></i> <?php echo htmlspecialchars($res_config['horario_linea_1']); ?></li>
                  <?php endif; ?>
                  
                  <?php if(!empty($res_config['horario_linea_2'])): ?>
                      <li><i class="far fa-clock"></i> <?php echo htmlspecialchars($res_config['horario_linea_2']); ?></li>
                  <?php endif; ?>
                  
                  <?php if(!empty($res_config['horario_linea_3'])): ?>
                      <li><i class="far fa-clock"></i> <?php echo htmlspecialchars($res_config['horario_linea_3']); ?></li>
                  <?php endif; ?>
              </ul>
          </div>
          <div class="footer-section">
              <h4>Enlaces</h4>
              <ul>
                  <li><a href="#" style="color:#bdc3c7; text-decoration:none;">Aviso Legal</a></li>
                  <li><a href="#" style="color:#bdc3c7; text-decoration:none;">Política de Privacidad</a></li>
                  <li><a href="#" style="color:#bdc3c7; text-decoration:none;">Mapa del Sitio</a></li>
              </ul>
          </div>
      </div>
      <div class="footer-bottom">
          <p>© <?php echo date('Y'); ?> Excmo. Ayuntamiento de Villamartín. Todos los derechos reservados.</p>
      </div>
  </footer>

</body>
</html>