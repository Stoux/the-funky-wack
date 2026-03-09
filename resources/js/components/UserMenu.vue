<script setup lang="ts">
import { useAuth } from '@/composables/useAuth';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link } from '@inertiajs/vue3';
import { User, Heart, History, ListMusic, Monitor, Radio, LogOut, LogIn, UserPlus, Shield } from 'lucide-vue-next';

const { user, isAuthenticated, logout } = useAuth();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="icon" class="rounded-full">
                <User class="h-4 w-4" />
                <span class="sr-only">User menu</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuItem as-child>
                <Link :href="route('live')" class="flex items-center cursor-pointer">
                    <Radio class="mr-2 h-4 w-4" />
                    Live
                </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <template v-if="isAuthenticated">
                <DropdownMenuLabel class="font-normal">
                    <div class="flex flex-col space-y-1">
                        <p class="text-sm font-medium leading-none">{{ user?.name }}</p>
                        <p class="text-xs leading-none text-muted-foreground">{{ user?.email }}</p>
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem as-child>
                    <Link :href="route('user.profile')" class="flex items-center cursor-pointer">
                        <User class="mr-2 h-4 w-4" />
                        Profile
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem as-child>
                    <Link :href="route('user.history')" class="flex items-center cursor-pointer">
                        <History class="mr-2 h-4 w-4" />
                        History
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem as-child>
                    <Link :href="route('user.favorites')" class="flex items-center cursor-pointer">
                        <Heart class="mr-2 h-4 w-4" />
                        Favorites
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem as-child>
                    <Link :href="route('user.playlists')" class="flex items-center cursor-pointer">
                        <ListMusic class="mr-2 h-4 w-4" />
                        Playlists
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem as-child>
                    <Link :href="route('user.devices')" class="flex items-center cursor-pointer">
                        <Monitor class="mr-2 h-4 w-4" />
                        Devices
                    </Link>
                </DropdownMenuItem>
                <template v-if="user?.is_admin">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <a href="/admin" class="flex items-center cursor-pointer">
                            <Shield class="mr-2 h-4 w-4" />
                            Admin
                        </a>
                    </DropdownMenuItem>
                </template>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="logout" class="cursor-pointer">
                    <LogOut class="mr-2 h-4 w-4" />
                    Log out
                </DropdownMenuItem>
            </template>
            <template v-else>
                <DropdownMenuItem as-child>
                    <Link :href="route('auth.login')" class="flex items-center cursor-pointer">
                        <LogIn class="mr-2 h-4 w-4" />
                        Login
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem as-child>
                    <Link :href="route('auth.register')" class="flex items-center cursor-pointer">
                        <UserPlus class="mr-2 h-4 w-4" />
                        Register
                    </Link>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
