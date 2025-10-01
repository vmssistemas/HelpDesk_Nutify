<!DOCTYPE html>
<html lang="pt-br">
  <head>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap"
      rel="stylesheet"
    />
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - Nutify Sistemas</title>
    <link rel="stylesheet" href="../assets/css/login.css?v=<?php echo time(); ?>">
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/x-icon">
    <!-- Adicionando Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  </head>
  <body>
      <!-- Botão de alternância para tema claro/escuro -->
  <div id="theme-toggle">
    <label class="switch">
      <input type="checkbox" id="theme-switch">
      <span class="slider round"></span>
    </label>
    <span id="theme-label">Tema Escuro</span>
  </div>
    <div id="container">
      <div id="profile">
        <img src="../assets/img/logo.png" alt="Nutify Sistemas" />
      </div>

      <!-- Mensagem de erro -->
      <div id="error-message" style="display: none; color: #EF5C22; margin-bottom: 16px; font-weight: 500;"></div>

      <!-- Formulário de Login -->
      <form id="login-form">
        <div class="input-group">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" placeholder="Digite seu e-mail" required />
        </div>

        <div class="input-group">
          <label for="password">Senha</label>
          <input type="password" id="password" name="senha" placeholder="Digite sua senha" required />
        </div>

        <button type="submit" id="loginButton">Entrar</button>
      </form>

      <footer>
        <!-- Ícones sociais -->
        <div class="social-icons">
          <a href="https://www.instagram.com/nutifysistemas/" target="_blank">
            <i class="fab fa-instagram social-icon" style="color: #E4405F;"></i>
          </a>
          <a href="#" target="_blank">
            <i class="fab fa-facebook social-icon" style="color: #1877F2;"></i>
          </a>
        </div>
      
        <!-- Texto do footer -->
        © 2025 <a href="#" target="_blank">Nutify Sistemas</a> 
      </footer>
    </div>

    <!-- Script para enviar o formulário via AJAX -->
    <script>
      document.getElementById('login-form').addEventListener('submit', function(event) {
        event.preventDefault(); // Impede o envio padrão do formulário

        const email = document.getElementById('email').value;
        const senha = document.getElementById('password').value;

        fetch('conexao_login.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `email=${encodeURIComponent(email)}&senha=${encodeURIComponent(senha)}`,
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Redireciona para a página principal após o login
              window.location.href = '../paginas/principal.php';
            } else {
              // Exibe a mensagem de erro na tela
              const errorMessage = document.getElementById('error-message');
              errorMessage.textContent = data.message;
              errorMessage.style.display = 'block';
            }
          })
          .catch(error => {
            console.error('Erro:', error);
          });
      });
    </script>
 <script>
  // Verifica o estado do tema no localStorage
  const themeSwitch = document.getElementById('theme-switch');
  const themeLabel = document.getElementById('theme-label');
  const body = document.body;

  // Função para aplicar o tema
  function applyTheme(isDark) {
    if (isDark) {
      body.classList.add('dark-theme'); // Adiciona a classe para o tema escuro
      body.style.backgroundImage = "url('../assets/img/bg-desktop.jpg')";
      themeLabel.textContent = "Tema Escuro";
    } else {
      body.classList.remove('dark-theme'); // Remove a classe do tema escuro
      body.style.backgroundImage = "url('../assets/img/cinza.png')";
      themeLabel.textContent = "Tema Claro";
    }
  }

  // Verifica o estado salvo no localStorage
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme === 'dark') {
    themeSwitch.checked = true;
    applyTheme(true);
  } else {
    themeSwitch.checked = false;
    applyTheme(false);
  }

  // Adiciona um listener para o botão de alternância
  themeSwitch.addEventListener('change', function() {
    if (this.checked) {
      localStorage.setItem('theme', 'dark');
      applyTheme(true);
    } else {
      localStorage.setItem('theme', 'light');
      applyTheme(false);
    }
  });
</script>
  </body>
</html>