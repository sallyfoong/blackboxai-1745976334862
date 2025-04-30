<?php
// index.php - Homepage with dynamic content
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>CME Website</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      font-family: 'Roboto', sans-serif;
    }
  </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <!-- 3D Animation Welcome Section -->
  <section class="relative w-full h-96 bg-white shadow-md flex items-center justify-center">
    <canvas id="threejs-canvas"></canvas>
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
      <h1 class="text-4xl font-bold text-gray-800 bg-white bg-opacity-70 px-6 py-3 rounded-md">
        Welcome to CME Website
      </h1>
    </div>
  </section>

  <!-- Product Pros Cards Section -->
  <section class="max-w-7xl mx-auto px-4 py-12">
    <!-- Content here -->
  </section>

</body>
</html>
