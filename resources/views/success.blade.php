{{-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
            background-color: #172248; /* deep navy */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #ffffff;
            width: 90%;
            max-width: 520px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            text-align: left;
        }
        .content { padding: 28px 28px 16px 28px; display: flex; gap: 16px; align-items: center; }
        .icon {
            width: 56px; height: 56px; flex: none;
            display: grid; place-items: center;
            color: #d44fb1; /* pinkish */
        }
        .title { margin: 0; color: #1f2937; font-size: 18px; font-weight: 600; }
        .action {
            display: block; text-align: center; text-decoration: none;
            background: #6b7bd6; /* periwinkle */
            color: #ffffff; font-weight: 600; letter-spacing: .2px;
            padding: 14px 20px; transition: background .2s ease-in-out;
        }
        .action:hover { background: #5d6dc6; }
        .action:active { background: #4e5fb5; }
    </style>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <meta name="robots" content="noindex">

    <!-- Remove the meta refresh above if you don't want auto redirect. -->
    <!-- Keep the manual Go Back button regardless. -->
</head>
<body>
    <div class="card" role="alert" aria-live="polite">
        <div class="content">
            <div class="icon" aria-hidden="true">
                <!-- Decorative glyph similar to screenshot -->
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9.75 3.5C6.85 3.5 4.5 5.85 4.5 8.75C4.5 11.65 6.85 14 9.75 14H11V20.5C11 21.33 11.67 22 12.5 22C13.33 22 14 21.33 14 20.5V4.75C14 4.06 13.44 3.5 12.75 3.5H9.75Z" fill="currentColor"/>
                </svg>
            </div>
            <h1 class="title">Your payment is successful !!</h1>
        </div>
        <button class="action" onclick="postMessage();">Go Back</button>
    </div>
    
    <script type="text/javascript">
        function postMessage(){
            Pay.postMessage('success'); 
        }
    </script>
</body>
</html> --}}



<!DOCTYPE html>
<html>
<head>
  <title>Thanks for your order!</title>
  <link rel="stylesheet" href="css/style.css">
  <script src="js/client.js" defer></script>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <section>
    <div class="product Box-root">
      <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="14px" height="16px" viewBox="0 0 14 16" version="1.1">
          <defs/>
          <g id="Flow" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
              <g id="0-Default" transform="translate(-121.000000, -40.000000)" fill="#E184DF">
                  <path d="M127,50 L126,50 C123.238576,50 121,47.7614237 121,45 C121,42.2385763 123.238576,40 126,40 L135,40 L135,56 L133,56 L133,42 L129,42 L129,56 L127,56 L127,50 Z M127,48 L127,42 L126,42 C124.343146,42 123,43.3431458 123,45 C123,46.6568542 124.343146,48 126,48 L127,48 Z" id="Pilcrow"/>
              </g>
          </g>
      </svg>
      <div class="description Box-root">
        <h3>Your payment is successful !!</h3>
      </div>
    </div>
    <button id="checkout-and-portal-button" onclick="postMessage();">Go Back</button>
  </section>
</body>
<script type='text/javascript'>

function postMessage(){

    Pay.postMessage('success');
}

</script>
</html>


