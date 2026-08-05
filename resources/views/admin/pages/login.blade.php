{{-- Admin sign-in. Standalone: no sidebar, no storefront chrome. --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in &middot; Brator Admin</title>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="flex min-h-screen items-center justify-center p-6">
        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="mb-8 flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-brand-500 text-lg font-bold text-white">B</span>
                <div>
                    <h1 class="text-lg font-semibold text-gray-800 dark:text-white/90">Brator Admin</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Staff sign in</p>
                </div>
            </div>

            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                    @foreach ($errors->all() as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            <form method="post" action="{{ route('admin.login.attempt', [], false) }}" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Password</label>
                    <input id="password" type="password" name="password" required
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input type="checkbox" name="remember" value="1" class="rounded border-gray-300" /> Keep me signed in
                </label>
                <button type="submit"
                    class="w-full rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">
                    Sign in
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('home', [], false) }}" class="hover:text-brand-500">&larr; Back to the shop</a>
            </p>
        </div>
    </div>
</body>
</html>
