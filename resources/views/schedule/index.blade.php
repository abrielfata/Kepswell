<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Manajemen Jadwal Live Session') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Form Buat Jadwal -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-semibold mb-4">Buat Jadwal Baru</h3>
                    
                    <form method="POST" action="{{ route('schedule.store') }}">
                        @csrf
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-medium text-sm text-gray-700">Host</label>
                                <select name="user_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Pilih Host</option>
                                    @foreach($hosts as $host)
                                        <option value="{{ $host->id }}">{{ $host->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block font-medium text-sm text-gray-700">Akun</label>
                                <select name="asset_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Pilih Akun</option>
                                    @foreach($assets as $asset)
                                        <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->platform }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block font-medium text-sm text-gray-700">Waktu Mulai</label>
                                <input type="datetime-local" name="scheduled_at" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            </div>

                            <div>
                                <label class="block font-medium text-sm text-gray-700">Durasi (menit)</label>
                                <input type="number" name="duration" value="120" min="30" max="480" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Buat Jadwal
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Daftar Jadwal -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-semibold mb-4">Daftar Jadwal</h3>
                    
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Host</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Akun</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">GMV</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($sessions as $session)
                                <tr class="{{ $session->status === 'cancelled' ? 'bg-red-50' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $session->user->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $session->asset->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $session->scheduled_at->format('d M Y H:i') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            {{ $session->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $session->status === 'scheduled' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $session->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}">
                                            {{ ucfirst($session->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $session->gmv ? 'Rp ' . number_format($session->gmv, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        @if($session->status === 'scheduled')
                                            <form method="POST" action="{{ route('schedule.destroy', $session) }}" onsubmit="return confirm('Yakin ingin membatalkan?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">Batalkan</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">Belum ada jadwal</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>