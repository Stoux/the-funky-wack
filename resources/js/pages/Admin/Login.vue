<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import {Label} from "@/components/ui/label";
import {Input} from "@/components/ui/input";
import InputError from "@/components/InputError.vue";
import {Button} from "@/components/ui/button";


const form = useForm({
    password: '',
});

function submit() {
    form.post(route('login.post'));
}

</script>

<template>
    <Head title="Login"/>

    <form @submit.prevent="submit" class="flex flex-col space-y-6 p-8">
        <h1 class="text-4xl">Hi!</h1>

        <div class="grid gap-2">
            <Label for="password">Password</Label>
            <Input id="password" type="password" name="password" ref="passwordInput" v-model="form.password"
                   placeholder="Password"/>
            <InputError :message="form.errors.password"/>
        </div>

        <Button type="submit" variant="default">
            Let's go!
        </Button>
    </form>
</template>
