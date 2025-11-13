<!DOCTYPE html>
<html>
<head>
     <meta charset="utf-8">
     <title>Laravel 9 Cashfree Payment Gateway Integration Tutorial</title>
     <meta name="csrf-token" content="{{ csrf_token() }}">
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
</head>
<body>
     <div class="container mt-3">
          <div class="row justify-content-center">
               <div class="col-12 col-md-6 mb-3">
                    <div class="card text-dark bg-light mb-3">
                    <div class="card-header text-center">
                         <h3 class="card-title " style="display: inline-block;">Create New Payment - Webappfix</h3>
                    </div>
                    <div class="card-body">
                         <form action="{{ route('store') }}" method="POST" name="laravel9-cashfree-integration">
                              @csrf
                              <div class="form-floating py-2">
                              <input type="text" class="form-control" name="name" id="name" value="{{ old('name') }}" placeholder="name">
                              <label for="name">Full Name</label>
                              </div>
                              <div class="form-floating py-2">
                              <input type="email" class="form-control" name="email" id="email" value="{{ old('email') }}" placeholder="email">
                              <label for="email">Email Address</label>
                              </div>
                              <div class="form-floating py-2">
                              <input type="text" class="form-control" name="mobile" id="mobile" value="{{ old('mobile') }}" placeholder="mobile">
                              <label for="mobile">Mobile Number</label>
                              </div>
                              <div class="form-floating py-2">
                              <input type="text" class="form-control" name="amount" id="amount" value="{{ old('amount') }}" placeholder="amount">
                              <label for="amount">Amount</label>
                              </div>
                              <button class="w-100 btn btn-lg btn-success" type="submit">Place Order</button>
                         </form>
                         @if ($errors->any())
                         <div class="alert alert-danger text-start" role="alert">
                              <strong>Opps!</strong> Something went wrong<br>
                              <ul>
                              @foreach ($errors->all() as $error)
                                   <li>{{ $error }}</li>
                              @endforeach
                              </ul>
                         </div>
                         @endif
                    </div>
                    </div>
               </div>
          </div>
     </div>
</body>
</html>